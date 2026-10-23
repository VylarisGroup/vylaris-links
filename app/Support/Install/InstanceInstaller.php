<?php

namespace App\Support\Install;

use App\Models\User;
use App\Support\EnvFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Richtet eine Instanz ein. Wird vom Web-Assistenten (/setup) und von "php artisan links:install" genutzt.
 * Jeder Schritt ist wiederholbar: vorhandene Werte, Tabellen und Konten bleiben erhalten.
 */
class InstanceInstaller
{
    public function __construct(private EnvFile $env)
    {
    }

    /**
     * @param  array{
     *   url: string, env: string, name?: ?string, registration?: bool, max_pages?: int, updates?: bool, optimize?: bool,
     *   db: array{driver: string, host?: string, port?: string|int, name: string, user?: string, pass?: ?string},
     *   mail?: ?array, admin?: array|callable|null
     * }  $c
     * @param  callable(string, string, string): void  $report  (Schritt, ok|warn|fail, Detail)
     */
    public function install(array $c, callable $report): bool
    {
        $steps = [
            '.env-Datei vorbereiten' => fn () => $this->prepareEnvFile(),
            'Instanz-Einstellungen' => fn () => $this->writeInstance($c),
            'Anwendungsschlüssel' => fn () => $this->ensureKey(),
            'Datenbank verbinden' => fn () => $this->connectDatabase($c['db']),
            'E-Mail-Versand' => fn () => $this->writeMail($c['mail'] ?? null),
            'Tabellen anlegen' => fn () => $this->migrate(),
            'Verzeichnisse und Speicher-Link' => fn () => $this->prepareStorage(),
            'Administrator-Konto' => fn () => $this->ensureAdmin($c['admin'] ?? null),
            'Zwischenspeicher' => fn () => $this->optimize($c),
            'Einrichtung abschliessen' => fn () => $this->finish(),
        ];

        foreach ($steps as $label => $run) {
            try {
                $result = $run();
            } catch (\Throwable $e) {
                $report($label, 'fail', $e->getMessage());

                return false;
            }
            [$state, $detail] = is_array($result) ? $result : ['ok', (string) $result];
            $report($label, $state, $detail);
        }

        return true;
    }

    // ------------------------------------------------------------------ Schritte

    private function prepareEnvFile(): string
    {
        if ($this->env->exists()) {
            return 'vorhanden, bestehende Werte bleiben erhalten';
        }
        $example = base_path('.env.example');
        if (is_file($example)) {
            copy($example, $this->env->path());
        } else {
            file_put_contents($this->env->path(), '');
        }
        @chmod($this->env->path(), 0600);

        return 'aus .env.example angelegt';
    }

    private function writeInstance(array $c): string
    {
        $url = rtrim((string) $c['url'], '/');
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException('Die Adresse ist ungültig: '.$url);
        }
        $envName = $c['env'] ?? 'production';
        if (! in_array($envName, ['production', 'local'], true)) {
            throw new \RuntimeException('Die Betriebsart muss production oder local sein.');
        }

        $pairs = [
            'APP_URL' => $url,
            'APP_ENV' => $envName,
            'APP_DEBUG' => $envName === 'local' ? 'true' : 'false',
            'APP_LOCALE' => 'de',
            'APP_FALLBACK_LOCALE' => 'de',
            'SESSION_SECURE_COOKIE' => str_starts_with(strtolower($url), 'https://') ? 'true' : 'false',
        ];
        if (! empty($c['name'])) {
            $pairs['APP_NAME'] = (string) $c['name'];
        }
        if (isset($c['registration'])) {
            $pairs['LINKS_REGISTRATION'] = $c['registration'] ? 'open' : 'closed';
        }
        if (isset($c['max_pages'])) {
            $pairs['LINKS_MAX_PAGES'] = (string) max(1, (int) $c['max_pages']);
        }
        if (isset($c['updates'])) {
            $pairs['UPDATER_ENABLED'] = $c['updates'] ? 'true' : 'false';
        }
        $this->env->setMany($pairs);

        config([
            'app.url' => $url, 'app.env' => $envName, 'app.debug' => $envName === 'local',
            'app.name' => $pairs['APP_NAME'] ?? config('app.name'),
        ]);

        return "{$url} ({$envName})";
    }

    private function ensureKey(): string
    {
        if ($this->env->get('APP_KEY')) {
            return 'vorhanden';
        }
        $key = 'base64:'.base64_encode(random_bytes(32));
        $this->env->set('APP_KEY', $key);
        config(['app.key' => $key]);

        return 'neu erzeugt';
    }

    private function connectDatabase(array $db): string
    {
        $driver = $db['driver'] ?? 'mysql';
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new \RuntimeException('Als Datenbank sind mysql und sqlite möglich.');
        }

        if ($driver === 'sqlite') {
            $file = (string) $db['name'];
            if ($file !== ':memory:' && ! is_file($file)) {
                @mkdir(dirname($file), 0775, true);
                if (! @touch($file)) {
                    throw new \RuntimeException('Die SQLite-Datei kann nicht angelegt werden: '.$file);
                }
            }
            $this->env->setMany(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file]);
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $file]);
        } else {
            $this->env->setMany([
                'DB_CONNECTION' => 'mysql', 'DB_HOST' => (string) $db['host'], 'DB_PORT' => (string) $db['port'],
                'DB_DATABASE' => (string) $db['name'], 'DB_USERNAME' => (string) $db['user'], 'DB_PASSWORD' => (string) ($db['pass'] ?? ''),
            ]);
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $db['host'], 'database.connections.mysql.port' => $db['port'],
                'database.connections.mysql.database' => $db['name'], 'database.connections.mysql.username' => $db['user'],
                'database.connections.mysql.password' => (string) ($db['pass'] ?? ''),
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

                    return 'MySQL verbunden, Datenbank neu angelegt';
                } catch (\Throwable $e2) {
                    $e = $e2;
                }
            }
            throw new \RuntimeException('Keine Verbindung zur Datenbank: '.\Illuminate\Support\Str::limit($e->getMessage(), 220));
        }

        return $driver === 'sqlite' ? 'SQLite verbunden' : 'MySQL/MariaDB verbunden';
    }

    private function tryCreateDatabase(): bool
    {
        $c = config('database.connections.mysql');
        if (! preg_match('/^[A-Za-z0-9_]+$/', (string) $c['database'])) {
            return false;
        }
        try {
            $pdo = new \PDO("mysql:host={$c['host']};port={$c['port']}", (string) $c['username'], (string) $c['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$c['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** null = unverändert, ['mailer' => 'log'] = nur ins Log schreiben, ['mailer' => 'smtp', ...] = SMTP. */
    private function writeMail(?array $m): string|array
    {
        if ($m === null) {
            return 'unverändert';
        }
        if (($m['mailer'] ?? '') === 'log') {
            $this->env->set('MAIL_MAILER', 'log');

            return ['warn', 'Kein SMTP gewählt – E-Mails (z. B. Passwort-Reset) werden nur ins Log geschrieben. SMTP lässt sich später in der .env nachtragen.'];
        }
        $this->env->setMany([
            'MAIL_MAILER' => 'smtp',
            'MAIL_SCHEME' => $m['scheme'] ?? 'null',
            'MAIL_HOST' => (string) $m['host'],
            'MAIL_PORT' => (string) $m['port'],
            'MAIL_USERNAME' => ($m['user'] ?? '') !== '' ? (string) $m['user'] : 'null',
            'MAIL_PASSWORD' => ($m['pass'] ?? '') !== '' ? (string) $m['pass'] : 'null',
            'MAIL_FROM_ADDRESS' => (string) $m['from'],
        ]);

        return 'SMTP '.$m['host'].':'.$m['port'];
    }

    private function migrate(): string
    {
        $code = Artisan::call('migrate', ['--force' => true]);
        if ($code !== 0) {
            throw new \RuntimeException('Die Migrationen sind fehlgeschlagen: '.\Illuminate\Support\Str::limit(trim(Artisan::output()), 300));
        }

        return 'Datenbank ist auf dem neuesten Stand';
    }

    private function prepareStorage(): string|array
    {
        foreach (['app/public', 'app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $d) {
            @mkdir(storage_path($d), 0775, true);
        }
        @mkdir(base_path('bootstrap/cache'), 0775, true);

        if (file_exists(public_path('storage')) || is_link(public_path('storage'))) {
            return 'vorhanden';
        }
        try {
            if (Artisan::call('storage:link') === 0) {
                return 'public/storage verknüpft';
            }
        } catch (\Throwable) {
        }

        return ['warn', 'Der Link public/storage konnte nicht angelegt werden (symlink gesperrt?). Ohne ihn sind hochgeladene Bilder nicht sichtbar.'];
    }

    /** @param  array|callable|null  $admin  name, email, password – oder eine Funktion, die das erst bei Bedarf liefert */
    private function ensureAdmin(array|callable|null $admin): string|array
    {
        if (is_callable($admin)) {
            $admin = $admin();
        }

        if ($admin === null) {
            if (Schema::hasTable('users') && User::where('is_admin', true)->exists()) {
                return 'vorhanden';
            }

            return ['warn', 'Kein Administrator angelegt. Später: php artisan links:make-admin deine@mail.ch'];
        }

        $existing = User::where('email', $admin['email'])->first();
        if ($existing) {
            $existing->forceFill(['is_admin' => true])->save();

            return $admin['email'].' ist jetzt Administrator';
        }

        $v = Validator::make($admin, [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);
        if ($v->fails()) {
            throw new \RuntimeException('Administrator-Konto: '.$v->errors()->first());
        }

        $user = new User(['name' => $admin['name'] ?: 'Admin', 'email' => $admin['email'], 'password' => $admin['password']]);
        $user->is_admin = true;
        $user->save();

        return $admin['email'].' angelegt';
    }

    private function optimize(array $c): string|array
    {
        if (($c['optimize'] ?? true) === false) {
            return 'übersprungen';
        }
        if (config('app.env') !== 'production') {
            Artisan::call('optimize:clear');

            return 'Zwischenspeicher geleert (Entwicklungsmodus)';
        }

        // Eigener Prozess: liest die frisch geschriebene .env sauber ein.
        $php = (new PhpExecutableFinder)->find(false);
        if (! $php || ! function_exists('proc_open')) {
            return ['warn', 'Übersprungen (kein PHP-Prozess möglich). Später: php artisan optimize'];
        }
        try {
            $p = new Process([$php, base_path('artisan'), 'optimize'], base_path(), null, null, 120);
            $p->run();
            if ($p->isSuccessful()) {
                return 'Konfiguration, Routen und Ansichten zwischengespeichert';
            }
        } catch (\Throwable) {
        }

        return ['warn', 'Der Cache-Aufbau ist fehlgeschlagen und wird übersprungen. Später: php artisan optimize'];
    }

    private function finish(): string|array
    {
        return SetupState::markInstalled()
            ? 'Assistent gesperrt'
            : ['warn', 'Die Marker-Datei storage/app/installed.json konnte nicht geschrieben werden. Der Assistent bleibt gesperrt, solange ein Administrator existiert.'];
    }
}
