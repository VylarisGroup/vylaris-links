<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\EnvFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class InstallCommand extends Command
{
    protected $signature = 'links:install
        {--check : Nur prüfen, ob die Installation vollständig ist (ändert nichts)}
        {--url= : Adresse der Seite (APP_URL)}
        {--env= : production oder local}
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

    protected $description = 'Richtet Vylaris Links ein: .env, Schlüssel, Datenbank, Migrationen, Admin-Konto und Caches (mehrfach ausführbar).';

    private EnvFile $env;

    public function handle(): int
    {
        $this->env = new EnvFile(app()->environmentFilePath());

        if ($this->option('check')) {
            return $this->check();
        }

        $this->components->info('Vylaris Links – Einrichtung');

        try {
            $this->prepareEnvFile();
            $this->configureApp();
            $this->ensureKey();
            if (! $this->configureDatabase()) {
                return self::FAILURE;
            }
            $this->configureMail();

            $this->components->task('Datenbank migrieren', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);
            if (! file_exists(public_path('storage')) && ! is_link(public_path('storage'))) {
                $this->components->task('Speicher-Link anlegen (public/storage)', fn () => $this->callSilently('storage:link') === 0);
            }
            $this->ensureStorageDirs();

            if (! $this->option('no-admin')) {
                $this->ensureAdmin();
            }

            if (! $this->option('no-optimize')) {
                if ($this->env->get('APP_ENV') === 'production') {
                    $this->components->task('Caches aufbauen', function () {
                        $ok = true;
                        foreach (['config:cache', 'route:cache', 'view:cache'] as $c) {
                            $ok = $this->callSilently($c) === 0 && $ok;
                        }

                        return $ok;
                    });
                } else {
                    $this->callSilently('optimize:clear');
                }
            }
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
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

    // ------------------------------------------------------------------ Schritte

    private function prepareEnvFile(): void
    {
        if ($this->env->exists()) {
            $this->components->twoColumnDetail('.env', 'vorhanden (bestehende Werte bleiben erhalten)');

            return;
        }
        $example = base_path('.env.example');
        if (is_file($example)) {
            copy($example, $this->env->path());
            @chmod($this->env->path(), 0600);
        } else {
            file_put_contents($this->env->path(), '');
        }
        $this->components->twoColumnDetail('.env', 'aus .env.example angelegt');
    }

    private function configureApp(): void
    {
        $current = $this->env->get('APP_URL');
        $default = ($current && ! str_contains($current, 'localhost')) ? $current : 'https://';

        $url = $this->option('url') ?: ($this->input->isInteractive()
            ? $this->ask('Adresse der Seite (APP_URL)', $default)
            : ($current ?: 'http://localhost'));
        $url = rtrim(trim((string) $url), '/');
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException('Die Adresse ist ungültig: '.$url.' (erwartet z. B. https://links.example.ch)');
        }

        $local = (bool) preg_match('#^https?://(localhost|127\.0\.0\.1|[^/]+\.(test|local|localhost))(:\d+)?$#i', $url);
        $envName = $this->option('env') ?: ($this->input->isInteractive()
            ? $this->choice('Umgebung', ['production', 'local'], $local ? 1 : 0)
            : ($local ? 'local' : 'production'));
        if (! in_array($envName, ['production', 'local'], true)) {
            throw new \RuntimeException('--env muss production oder local sein.');
        }

        $this->env->setMany([
            'APP_URL' => $url,
            'APP_ENV' => $envName,
            'APP_DEBUG' => $envName === 'local' ? 'true' : 'false',
            'APP_LOCALE' => 'de',
            'APP_FALLBACK_LOCALE' => 'de',
        ]);
        config(['app.url' => $url, 'app.env' => $envName, 'app.debug' => $envName === 'local']);
        $this->components->twoColumnDetail('Adresse / Umgebung', "{$url} / {$envName}");
    }

    private function ensureKey(): void
    {
        if ($this->env->get('APP_KEY')) {
            $this->components->twoColumnDetail('APP_KEY', 'vorhanden');

            return;
        }
        $key = 'base64:'.base64_encode(random_bytes(32));
        $this->env->set('APP_KEY', $key);
        config(['app.key' => $key]);
        $this->components->twoColumnDetail('APP_KEY', 'neu erzeugt');
    }

    private function configureDatabase(): bool
    {
        $driver = $this->option('db-driver') ?: $this->env->get('DB_CONNECTION') ?: 'mysql';
        if ($this->input->isInteractive() && ! $this->option('db-driver')) {
            $driver = $this->choice('Datenbank', ['mysql', 'sqlite'], $driver === 'sqlite' ? 1 : 0);
        }
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new \RuntimeException('--db-driver muss mysql oder sqlite sein.');
        }

        if ($driver === 'sqlite') {
            $file = $this->option('db-name') ?: $this->env->get('DB_DATABASE');
            if (! $file || ! str_contains($file, '.')) {
                $file = database_path('database.sqlite');
            }
            if ($this->input->isInteractive() && ! $this->option('db-name')) {
                $file = $this->ask('Pfad der SQLite-Datei', $file);
            }
            if ($file !== ':memory:' && ! is_file($file)) {
                @mkdir(dirname($file), 0775, true);
                touch($file);
            }
            $this->env->setMany(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file]);
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $file]);
        } else {
            $opt = fn (string $o, string $envKey, string $fallback) => $this->option($o) ?? $this->env->get($envKey) ?: $fallback;
            $host = $opt('db-host', 'DB_HOST', '127.0.0.1');
            $port = $opt('db-port', 'DB_PORT', '3306');
            $name = $opt('db-name', 'DB_DATABASE', 'vylaris_links');
            $user = $opt('db-user', 'DB_USERNAME', 'root');
            $pass = $this->option('db-pass') ?? (getenv('LINKS_DB_PASSWORD') !== false ? getenv('LINKS_DB_PASSWORD') : $this->env->get('DB_PASSWORD'));

            if ($this->input->isInteractive()) {
                $host = $this->option('db-host') ?? $this->ask('Datenbank-Host', $host);
                $port = $this->option('db-port') ?? $this->ask('Datenbank-Port', $port);
                $name = $this->option('db-name') ?? $this->ask('Datenbank-Name', $name);
                $user = $this->option('db-user') ?? $this->ask('Datenbank-Benutzer', $user);
                if ($this->option('db-pass') === null && getenv('LINKS_DB_PASSWORD') === false) {
                    $typed = $this->secret('Datenbank-Passwort (leer lassen, um das bisherige zu behalten)');
                    $pass = $typed !== null && $typed !== '' ? $typed : $pass;
                }
            }

            $this->env->setMany([
                'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => (string) $port,
                'DB_DATABASE' => $name, 'DB_USERNAME' => $user, 'DB_PASSWORD' => (string) $pass,
            ]);
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $host, 'database.connections.mysql.port' => $port,
                'database.connections.mysql.database' => $name, 'database.connections.mysql.username' => $user,
                'database.connections.mysql.password' => (string) $pass,
            ]);
        }

        DB::purge();

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            if ($driver === 'mysql' && $this->tryCreateDatabase()) {
                DB::purge();
                try {
                    DB::connection()->getPdo();
                } catch (\Throwable $e2) {
                    $e = $e2;
                }
            }
            if (! $this->databaseReachable()) {
                $this->components->error('Keine Verbindung zur Datenbank: '.Str::limit($e->getMessage(), 220));
                $this->line('Die Werte stehen in der .env. Korrigiere sie und führe <info>php artisan links:install</info> erneut aus.');

                return false;
            }
        }
        $this->components->twoColumnDetail('Datenbank', 'Verbindung steht ('.$driver.')');

        return true;
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

    /** Legt die MySQL-Datenbank an, falls sie fehlt und der Benutzer das darf. */
    private function tryCreateDatabase(): bool
    {
        $c = config('database.connections.mysql');
        if (! preg_match('/^[A-Za-z0-9_]+$/', (string) $c['database'])) {
            return false;
        }
        try {
            $pdo = new \PDO("mysql:host={$c['host']};port={$c['port']}", (string) $c['username'], (string) $c['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$c['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->components->twoColumnDetail('Datenbank', 'neu angelegt');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function configureMail(): void
    {
        if (! $this->input->isInteractive() || $this->env->get('MAIL_HOST') && $this->env->get('MAIL_HOST') !== '127.0.0.1') {
            return;
        }
        if (! $this->confirm('E-Mail-Versand (SMTP) jetzt einrichten? (für Passwort-Reset und Wochenbericht)', false)) {
            $this->line('  Hinweis: Ohne SMTP-Zugang in der .env werden keine E-Mails verschickt (MAIL_* anpassen).');

            return;
        }
        $host = $this->ask('SMTP-Host');
        $port = $this->ask('SMTP-Port', '587');
        $user = $this->ask('SMTP-Benutzer');
        $pass = $this->secret('SMTP-Passwort');
        $from = $this->ask('Absender-Adresse', $this->env->get('MAIL_FROM_ADDRESS') ?: 'links@'.parse_url((string) $this->env->get('APP_URL'), PHP_URL_HOST));

        $this->env->setMany([
            'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => (int) $port === 465 ? 'smtps' : 'null',
            'MAIL_HOST' => $host, 'MAIL_PORT' => (string) $port, 'MAIL_USERNAME' => $user,
            'MAIL_PASSWORD' => (string) $pass, 'MAIL_FROM_ADDRESS' => $from,
        ]);
    }

    private function ensureStorageDirs(): void
    {
        foreach (['app/public', 'app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $d) {
            @mkdir(storage_path($d), 0775, true);
        }
        @mkdir(base_path('bootstrap/cache'), 0775, true);
    }

    private function ensureAdmin(): void
    {
        $email = $this->option('admin-email');

        if (! $email && User::where('is_admin', true)->exists()) {
            $this->components->twoColumnDetail('Admin-Konto', 'vorhanden');

            return;
        }

        if (! $email && ! $this->input->isInteractive()) {
            $this->components->warn('Kein Admin-Konto angelegt (--admin-email fehlt). Später: php artisan links:make-admin deine@mail.ch');

            return;
        }

        $email ??= $this->ask('E-Mail des Admin-Kontos');
        $existing = User::where('email', $email)->first();
        if ($existing) {
            $existing->forceFill(['is_admin' => true])->save();
            $this->components->twoColumnDetail('Admin-Konto', $email.' ist jetzt Admin');

            return;
        }

        $name = $this->option('admin-name') ?: ($this->input->isInteractive() ? $this->ask('Name', 'Admin') : 'Admin');
        $pass = $this->option('admin-password') ?: (getenv('LINKS_ADMIN_PASSWORD') ?: null);
        $generated = false;
        if (! $pass && $this->input->isInteractive()) {
            $pass = $this->secret('Passwort (leer lassen für ein zufälliges)') ?: null;
        }
        if (! $pass) {
            $pass = Str::password(16, symbols: false);
            $generated = true;
        }

        $v = Validator::make(['email' => $email, 'password' => $pass], [
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);
        if ($v->fails()) {
            throw new \RuntimeException('Admin-Konto: '.$v->errors()->first());
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $pass]);
        $user->is_admin = true;
        $user->save();

        $this->components->twoColumnDetail('Admin-Konto', $email.' angelegt');
        if ($generated) {
            $this->components->warn('Zufälliges Passwort (wird nur jetzt angezeigt): '.$pass);
        }
    }

    // ------------------------------------------------------------------ Prüfung

    private function check(): int
    {
        $rows = [];
        $add = function (string $label, string $state, string $hint = '') use (&$rows) {
            $rows[] = [$label, $state, $hint];
        };

        $add('PHP '.PHP_VERSION, version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'fail', 'PHP 8.3 oder neuer nötig');
        foreach (['mbstring', 'openssl', 'pdo', 'zip', 'fileinfo', 'gd', 'intl'] as $ext) {
            $add("Erweiterung {$ext}", extension_loaded($ext) ? 'ok' : ($ext === 'zip' || $ext === 'gd' || $ext === 'intl' ? 'warn' : 'fail'), $ext === 'zip' ? 'Ohne zip funktioniert das Update-System nicht' : '');
        }

        $add('.env vorhanden', $this->env->exists() ? 'ok' : 'fail', 'php artisan links:install ausführen');
        $add('APP_KEY gesetzt', $this->env->get('APP_KEY') ? 'ok' : 'fail', 'Ohne Schlüssel startet die Seite nicht');
        $prod = $this->env->get('APP_ENV') === 'production';
        $add('APP_DEBUG aus (Produktion)', ! $prod || $this->env->get('APP_DEBUG') === 'false' ? 'ok' : 'warn', 'APP_DEBUG=false setzen, sonst werden Fehlerdetails öffentlich angezeigt');
        $url = (string) $this->env->get('APP_URL');
        $add('APP_URL gesetzt', $url !== '' && ! str_contains($url, 'localhost') || ! $prod ? 'ok' : 'warn', 'Aktuell: '.($url ?: 'leer'));

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

        foreach (['storage', 'storage/logs', 'storage/framework', 'bootstrap/cache'] as $d) {
            $add("{$d} beschreibbar", is_writable(base_path($d)) ? 'ok' : 'fail', 'chmod -R ug+w '.$d.' (und passenden Besitzer setzen)');
        }
        $add('public/storage-Link', file_exists(public_path('storage')) ? 'ok' : 'warn', 'php artisan storage:link (sonst sind hochgeladene Bilder nicht sichtbar)');
        $add('Gebaute Assets (public/build)', is_file(public_path('build/manifest.json')) ? 'ok' : 'fail', 'Fehlt → Seite liefert Fehler 500. npm ci && npm run build oder source-code.zip aus dem Release nutzen');
        $add('Version', 'ok', 'v'.config('updater.version'));

        $icons = ['ok' => '<info>✓</info>', 'warn' => '<comment>!</comment>', 'fail' => '<error> ✗ </error>'];
        $this->line('<options=bold>Prüfung</>');
        $fails = 0;
        foreach ($rows as [$label, $state, $hint]) {
            $fails += $state === 'fail' ? 1 : 0;
            $this->line(sprintf('  %s %s%s', $icons[$state], $label, $state !== 'ok' && $hint ? "  <fg=gray>— {$hint}</>" : ($state === 'ok' && str_starts_with($hint, 'v') ? "  <fg=gray>{$hint}</>" : '')));
        }

        $this->newLine();
        $fails === 0 ? $this->components->info('Alles bereit.') : $this->components->error($fails.' Punkt(e) müssen behoben werden.');

        return $fails === 0 ? self::SUCCESS : self::FAILURE;
    }
}
