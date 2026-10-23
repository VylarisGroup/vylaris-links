<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\EnvFile;
use App\Support\Install\InstanceInstaller;
use App\Support\Install\Requirements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'links:install
        {--check : Nur prüfen, ob die Installation vollständig ist (ändert nichts)}
        {--url= : Adresse der Seite (APP_URL)}
        {--env= : production oder local}
        {--name= : Name der Instanz (APP_NAME)}
        {--db-driver= : mysql oder sqlite}
        {--db-host=}
        {--db-port=}
        {--db-name= : Datenbankname (bei sqlite: Dateipfad)}
        {--db-user=}
        {--db-pass= : Datenbank-Passwort (besser: Umgebungsvariable LINKS_DB_PASSWORD)}
        {--admin-name=}
        {--admin-email=}
        {--admin-password= : Admin-Passwort (besser: Umgebungsvariable LINKS_ADMIN_PASSWORD)}
        {--no-admin : Kein Admin-Konto anlegen}
        {--no-optimize : Caches nicht aufbauen}';

    protected $description = 'Richtet Vylaris Links ein: .env, Schlüssel, Datenbank, Migrationen, Admin-Konto und Caches (mehrfach ausführbar). Alternative: Web-Assistent unter /setup.';

    private EnvFile $env;

    private EnvFile $example;

    private ?string $generatedPassword = null;

    public function handle(): int
    {
        $this->env = new EnvFile(app()->environmentFilePath());
        $this->example = new EnvFile(base_path('.env.example'));

        if ($this->option('check')) {
            return $this->check();
        }

        $this->components->info('Vylaris Links – Einrichtung');

        try {
            $config = $this->gather();
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $ok = (new InstanceInstaller($this->env))->install($config, function (string $label, string $state, string $detail) {
            $this->components->twoColumnDetail($label, match ($state) {
                'ok' => $detail,
                'warn' => '<comment>'.$detail.'</comment>',
                default => '<error>'.$detail.'</error>',
            });
        });

        if (! $ok) {
            $this->newLine();
            $this->line('Korrigiere den Fehler und führe <info>php artisan links:install</info> erneut aus – bereits Erledigtes bleibt erhalten.');

            return self::FAILURE;
        }

        if ($this->generatedPassword) {
            $this->components->warn('Zufälliges Admin-Passwort (wird nur jetzt angezeigt): '.$this->generatedPassword);
        }

        $this->newLine();
        $code = $this->check();
        $this->newLine();
        $this->line('<options=bold>Noch zu tun:</>');
        $this->line('  • Webserver auf den Ordner <info>public/</info> zeigen lassen.');
        $this->line('  • Cron für den Scheduler (Wochenbericht, nächtliche Update-Prüfung):');
        $this->line('    <comment>* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1</comment>');

        return $code;
    }

    // ------------------------------------------------------------------ Eingaben

    private function gather(): array
    {
        $interactive = $this->input->isInteractive();
        $cur = fn (string $k) => $this->env->get($k) ?? $this->example->get($k);

        $currentUrl = $this->env->get('APP_URL');
        $default = ($currentUrl && ! str_contains($currentUrl, 'localhost')) ? $currentUrl : 'https://';
        $url = rtrim(trim((string) ($this->option('url') ?: ($interactive ? $this->ask('Adresse der Seite (APP_URL)', $default) : ($currentUrl ?: 'http://localhost')))), '/');

        $local = (bool) preg_match('#^https?://(localhost|127\.0\.0\.1|[^/]+\.(test|local|localhost))(:\d+)?$#i', $url);
        $envName = $this->option('env') ?: ($interactive ? $this->choice('Umgebung', ['production', 'local'], $local ? 1 : 0) : ($local ? 'local' : 'production'));

        $driver = $this->option('db-driver') ?: $this->env->get('DB_CONNECTION') ?: 'mysql';
        if ($interactive && ! $this->option('db-driver')) {
            $driver = $this->choice('Datenbank', ['mysql', 'sqlite'], $driver === 'sqlite' ? 1 : 0);
        }

        if ($driver === 'sqlite') {
            $file = $this->option('db-name') ?: $this->env->get('DB_DATABASE');
            if (! $file || ! str_contains($file, '.')) {
                $file = database_path('database.sqlite');
            }
            if ($interactive && ! $this->option('db-name')) {
                $file = $this->ask('Pfad der SQLite-Datei', $file);
            }
            $db = ['driver' => 'sqlite', 'name' => $file];
        } else {
            $host = $this->option('db-host') ?? $cur('DB_HOST') ?: '127.0.0.1';
            $port = $this->option('db-port') ?? $cur('DB_PORT') ?: '3306';
            $name = $this->option('db-name') ?? $cur('DB_DATABASE') ?: 'vylaris_links';
            $user = $this->option('db-user') ?? $cur('DB_USERNAME') ?: 'root';
            $pass = $this->option('db-pass') ?? (getenv('LINKS_DB_PASSWORD') !== false ? getenv('LINKS_DB_PASSWORD') : $this->env->get('DB_PASSWORD'));

            if ($interactive) {
                $host = $this->option('db-host') ?? $this->ask('Datenbank-Host', $host);
                $port = $this->option('db-port') ?? $this->ask('Datenbank-Port', $port);
                $name = $this->option('db-name') ?? $this->ask('Datenbank-Name', $name);
                $user = $this->option('db-user') ?? $this->ask('Datenbank-Benutzer', $user);
                if ($this->option('db-pass') === null && getenv('LINKS_DB_PASSWORD') === false) {
                    $typed = $this->secret('Datenbank-Passwort (leer lassen, um das bisherige zu behalten)');
                    $pass = $typed !== null && $typed !== '' ? $typed : $pass;
                }
            }
            $db = ['driver' => 'mysql', 'host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'pass' => (string) $pass];
        }

        return [
            'url' => $url,
            'env' => $envName,
            'name' => $this->option('name'),
            'db' => $db,
            'mail' => $interactive ? $this->askMail() : null,
            'admin' => $this->option('no-admin') ? null : fn () => $this->askAdmin(),
            'optimize' => ! $this->option('no-optimize'),
        ];
    }

    private function askMail(): ?array
    {
        $host = $this->env->get('MAIL_HOST');
        if ($host && $host !== '127.0.0.1') {
            return null;
        }
        if (! $this->confirm('E-Mail-Versand (SMTP) jetzt einrichten? (für Passwort-Reset und Wochenbericht)', false)) {
            return null;
        }
        $host = $this->ask('SMTP-Host');
        $port = (int) $this->ask('SMTP-Port', '587');
        $user = $this->ask('SMTP-Benutzer');
        $pass = $this->secret('SMTP-Passwort');
        $from = $this->ask('Absender-Adresse', $this->env->get('MAIL_FROM_ADDRESS') ?: 'links@'.parse_url((string) $this->env->get('APP_URL'), PHP_URL_HOST));

        return ['mailer' => 'smtp', 'host' => $host, 'port' => $port, 'scheme' => $port === 465 ? 'smtps' : null, 'user' => $user, 'pass' => $pass, 'from' => $from];
    }

    /** Wird erst aufgerufen, wenn die Datenbank bereit ist. */
    private function askAdmin(): ?array
    {
        $email = $this->option('admin-email');

        if (! $email && User::where('is_admin', true)->exists()) {
            return null;
        }
        if (! $email && ! $this->input->isInteractive()) {
            return null;
        }

        $email ??= $this->ask('E-Mail des Admin-Kontos');
        if (User::where('email', $email)->exists()) {
            return ['name' => 'Admin', 'email' => $email, 'password' => ''];
        }

        $interactive = $this->input->isInteractive();
        $name = $this->option('admin-name') ?: ($interactive ? $this->ask('Name', 'Admin') : 'Admin');
        $pass = $this->option('admin-password') ?: (getenv('LINKS_ADMIN_PASSWORD') ?: null);
        if (! $pass && $interactive) {
            $pass = $this->secret('Passwort (leer lassen für ein zufälliges)') ?: null;
        }
        if (! $pass) {
            $pass = $this->generatedPassword = Str::password(16, symbols: false);
        }

        return ['name' => $name, 'email' => $email, 'password' => $pass];
    }

    // ------------------------------------------------------------------ Prüfung

    private function check(): int
    {
        $rows = Requirements::system($this->env);
        $add = function (string $label, string $state, string $hint = '') use (&$rows) {
            $rows[] = ['label' => $label, 'state' => $state, 'hint' => $hint];
        };

        $add('.env vorhanden', $this->env->exists() ? 'ok' : 'fail', 'php artisan links:install ausführen');
        $add('APP_KEY gesetzt', $this->env->get('APP_KEY') ? 'ok' : 'fail', 'Ohne Schlüssel startet die Seite nicht');
        $prod = $this->env->get('APP_ENV') === 'production';
        $add('APP_DEBUG aus (Produktion)', ! $prod || $this->env->get('APP_DEBUG') === 'false' ? 'ok' : 'warn', 'APP_DEBUG=false setzen, sonst werden Fehlerdetails öffentlich angezeigt');
        $url = (string) $this->env->get('APP_URL');
        $add('APP_URL gesetzt', ($url !== '' && ! str_contains($url, 'localhost')) || ! $prod ? 'ok' : 'warn', 'Aktuell: '.($url ?: 'leer'));

        $dbOk = $this->databaseReachable();
        $add('Datenbank erreichbar', $dbOk ? 'ok' : 'fail', 'DB_* in der .env prüfen');
        if ($dbOk) {
            try {
                $m = app('migrator');
                $ran = $m->repositoryExists() ? $m->getRepository()->getRan() : [];
                $files = array_keys($m->getMigrationFiles([database_path('migrations')]));
                $pending = count(array_diff($files, $ran));
                $add('Migrationen ausgeführt', $pending === 0 ? 'ok' : 'fail', $pending.' ausstehend – php artisan migrate --force');
                $add('Admin-Konto vorhanden', Schema::hasTable('users') && User::where('is_admin', true)->exists() ? 'ok' : 'warn', 'php artisan links:make-admin deine@mail.ch');
            } catch (\Throwable $e) {
                $add('Migrationen prüfen', 'fail', Str::limit($e->getMessage(), 120));
            }
        }
        $add('Version', 'ok', 'v'.config('updater.version'));

        $icons = ['ok' => '<info>✓</info>', 'warn' => '<comment>!</comment>', 'fail' => '<error> ✗ </error>'];
        $this->line('<options=bold>Prüfung</>');
        foreach ($rows as ['label' => $label, 'state' => $state, 'hint' => $hint]) {
            $extra = match (true) {
                $state !== 'ok' && $hint !== '' => "  <fg=gray>— {$hint}</>",
                $label === 'Version' => "  <fg=gray>{$hint}</>",
                default => '',
            };
            $this->line(sprintf('  %s %s%s', $icons[$state], $label, $extra));
        }

        $fails = Requirements::blocking($rows);
        $this->newLine();
        $fails === 0 ? $this->components->info('Alles bereit.') : $this->components->error($fails.' Punkt(e) müssen behoben werden.');

        return $fails === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
