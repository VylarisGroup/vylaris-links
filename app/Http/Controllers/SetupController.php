<?php

namespace App\Http\Controllers;

use App\Support\EnvFile;
use App\Support\Install\DatabaseProbe;
use App\Support\Install\InstanceInstaller;
use App\Support\Install\Requirements;
use App\Support\Install\SetupState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Geführter Einrichtungsassistent. Läuft bewusst ohne Session, Cookies und Datenbank:
 * Geschützt wird er durch ein Token, das nur mit Zugriff auf den Server lesbar ist
 * (Datei storage/app/setup-token). Nach der Einrichtung ist er vollständig gesperrt.
 */
class SetupController extends Controller
{
    private const SECURITY_HEADERS = [
        'Cache-Control' => 'no-store',
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'none'; base-uri 'none'",
    ];

    public function index(Request $request)
    {
        if (SetupState::isInstalled()) {
            return response('', 302, ['Location' => '/']);
        }

        $token = SetupState::token(create: true);

        return response()->view('setup.index', [
            'tokenReady' => $token !== null,
            'tokenFile' => 'storage/app/setup-token',
        ])->withHeaders(self::SECURITY_HEADERS);
    }

    /** Token prüfen; liefert Systemprüfung und Vorschlagswerte. */
    public function start(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $env = new EnvFile(app()->environmentFilePath());
        $example = new EnvFile(base_path('.env.example'));
        $get = fn (string $k, ?string $d = null) => $env->get($k) ?? $example->get($k) ?? $d;

        $proto = strtolower((string) $request->header('X-Forwarded-Proto'));
        $secure = $request->isSecure() || $proto === 'https';
        $current = $env->get('APP_URL');
        $url = ($current && ! str_contains($current, 'localhost')) ? $current : ($secure ? 'https://' : 'http://').$request->getHttpHost();

        $rows = Requirements::system($env);

        return $this->json([
            'requirements' => $rows,
            'blocking' => Requirements::blocking($rows),
            'defaults' => [
                'instance' => [
                    'name' => $get('APP_NAME', 'Vylaris Links'),
                    'url' => $url,
                    'env' => 'production',
                    'registration' => $get('LINKS_REGISTRATION', 'open') !== 'closed',
                    'max_pages' => (int) $get('LINKS_MAX_PAGES', '3'),
                    'updates' => $get('UPDATER_ENABLED', 'true') !== 'false',
                ],
                'db' => [
                    'driver' => $env->get('DB_CONNECTION') === 'sqlite' ? 'sqlite' : 'mysql',
                    'host' => $get('DB_HOST', '127.0.0.1'),
                    'port' => $get('DB_PORT', '3306'),
                    'name' => $get('DB_DATABASE', 'vylaris_links'),
                    'user' => $get('DB_USERNAME', 'root'),
                ],
                'mail' => ['from' => $get('MAIL_FROM_ADDRESS', 'links@'.$request->getHost())],
            ],
        ]);
    }

    public function database(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $data = $this->validated($request, ['db']);

        return $this->json(DatabaseProbe::test($this->dbConfig($data['db'])));
    }

    public function mail(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $data = $this->validated($request, ['mail'], ['to' => ['required', 'email', 'max:120']]);
        if (($data['mail']['mode'] ?? '') !== 'smtp') {
            return $this->json(['ok' => false, 'message' => 'Wähle zuerst „SMTP-Zugang“.']);
        }
        $m = $this->mailConfig($data['mail']);

        config(['mail.mailers.setup_probe' => [
            'transport' => 'smtp', 'scheme' => $m['scheme'], 'host' => $m['host'], 'port' => $m['port'],
            'username' => $m['user'] !== '' ? $m['user'] : null, 'password' => $m['pass'] !== '' ? $m['pass'] : null, 'timeout' => 10,
        ]]);
        Mail::purge('setup_probe');

        try {
            Mail::mailer('setup_probe')->raw(
                "Das ist eine Testnachricht des Einrichtungsassistenten.\nWenn du sie liest, funktioniert der E-Mail-Versand.",
                fn ($message) => $message->to($data['to'])->from($m['from'], 'Vylaris Links')->subject('Testnachricht – Vylaris Links Einrichtung'),
            );
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => 'Der Versand ist fehlgeschlagen: '.\Illuminate\Support\Str::limit($e->getMessage(), 240)]);
        }

        return $this->json(['ok' => true, 'message' => 'Testnachricht an '.$data['to'].' gesendet. Prüfe dein Postfach (auch den Spam-Ordner).']);
    }

    public function install(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $data = $this->validated($request, ['instance', 'db', 'admin', 'mail']);

        $lock = @fopen(SetupState::dir().'/setup.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->json(['ok' => false, 'message' => 'Die Einrichtung läuft bereits.'], 409);
        }

        @set_time_limit(300);
        $steps = [];
        $i = $data['instance'];

        $ok = (new InstanceInstaller(new EnvFile(app()->environmentFilePath())))->install([
            'url' => $i['url'],
            'env' => $i['env'],
            'name' => $i['name'],
            'registration' => (bool) ($i['registration'] ?? false),
            'max_pages' => (int) $i['max_pages'],
            'updates' => (bool) ($i['updates'] ?? false),
            'db' => $this->dbConfig($data['db']),
            'mail' => ($data['mail']['mode'] ?? 'log') === 'smtp'
                ? ['mailer' => 'smtp'] + $this->mailConfig($data['mail'])
                : ['mailer' => 'log'],
            'admin' => ['name' => $data['admin']['name'], 'email' => $data['admin']['email'], 'password' => $data['admin']['password']],
        ], function (string $label, string $state, string $detail) use (&$steps) {
            $steps[] = ['label' => $label, 'state' => $state, 'detail' => $detail];
        });

        flock($lock, LOCK_UN);
        fclose($lock);
        if ($ok) {
            @unlink(SetupState::dir().'/setup.lock');
        }

        return $this->json([
            'ok' => $ok,
            'steps' => $steps,
            'login' => rtrim($i['url'], '/').'/anmelden',
            'cron' => '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
        ]);
    }

    // ------------------------------------------------------------------ Hilfen

    /** Das Token im Header ersetzt hier den CSRF-Schutz: Es steckt in keinem Cookie und wird nie automatisch mitgeschickt. */
    private function guard(Request $request): ?JsonResponse
    {
        $request->headers->set('Accept', 'application/json');

        if (SetupState::isInstalled()) {
            return $this->json(['message' => 'Die Einrichtung ist bereits abgeschlossen.'], 403);
        }
        if (! SetupState::verify($request->header('X-Setup-Token'))) {
            usleep(500_000); // bremst Raten
            return $this->json(['message' => 'Das Token stimmt nicht. Du findest es in der Datei '.'storage/app/setup-token'.' auf dem Server.'], 403);
        }

        return null;
    }

    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)->withHeaders(self::SECURITY_HEADERS);
    }

    private function validated(Request $request, array $sections, array $extra = []): array
    {
        $input = $request->json()->all();
        $bad = false;
        array_walk_recursive($input, function ($v) use (&$bad) {
            if (is_string($v) && preg_match('/[\r\n\0]/', $v)) {
                $bad = true;
            }
        });
        if ($bad) {
            abort(422, 'Eingaben dürfen keine Zeilenumbrüche enthalten.');
        }

        $all = [
            'instance' => [
                'instance.name' => ['required', 'string', 'max:60'],
                'instance.url' => ['required', 'url:http,https', 'max:200'],
                'instance.env' => ['required', 'in:production,local'],
                'instance.registration' => ['boolean'],
                'instance.max_pages' => ['required', 'integer', 'between:1,50'],
                'instance.updates' => ['boolean'],
            ],
            'db' => [
                'db.driver' => ['required', 'in:mysql,sqlite'],
                'db.host' => ['exclude_if:db.driver,sqlite', 'required', 'regex:/^[A-Za-z0-9._\-:\[\]]+$/', 'max:120'],
                'db.port' => ['exclude_if:db.driver,sqlite', 'required', 'integer', 'between:1,65535'],
                'db.name' => ['exclude_if:db.driver,sqlite', 'required', 'regex:/^[A-Za-z0-9_]{1,64}$/'],
                'db.user' => ['exclude_if:db.driver,sqlite', 'required', 'string', 'max:80'],
                'db.pass' => ['nullable', 'string', 'max:200'],
            ],
            'admin' => [
                'admin.name' => ['required', 'string', 'max:80'],
                'admin.email' => ['required', 'email', 'max:120'],
                'admin.password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            ],
            'mail' => [
                'mail.mode' => ['required', 'in:log,smtp'],
                'mail.host' => ['exclude_unless:mail.mode,smtp', 'required', 'regex:/^[A-Za-z0-9._\-]+$/', 'max:120'],
                'mail.port' => ['exclude_unless:mail.mode,smtp', 'required', 'integer', 'between:1,65535'],
                'mail.security' => ['exclude_unless:mail.mode,smtp', 'required', 'in:starttls,ssl'],
                'mail.user' => ['exclude_unless:mail.mode,smtp', 'nullable', 'string', 'max:120'],
                'mail.pass' => ['exclude_unless:mail.mode,smtp', 'nullable', 'string', 'max:200'],
                'mail.from' => ['exclude_unless:mail.mode,smtp', 'required', 'email', 'max:120'],
            ],
        ];

        $rules = $extra;
        foreach ($sections as $s) {
            $rules += $all[$s];
        }

        return Validator::make($input, $rules, [], [
            'instance.name' => 'Name', 'instance.url' => 'Adresse', 'instance.max_pages' => 'Seiten pro Konto',
            'db.host' => 'Host', 'db.port' => 'Port', 'db.name' => 'Datenbankname', 'db.user' => 'Benutzer',
            'admin.name' => 'Name', 'admin.email' => 'E-Mail', 'admin.password' => 'Passwort',
            'mail.host' => 'SMTP-Host', 'mail.port' => 'Port', 'mail.from' => 'Absender', 'to' => 'Empfänger',
        ])->validate();
    }

    private function dbConfig(array $db): array
    {
        if ($db['driver'] === 'sqlite') {
            return ['driver' => 'sqlite', 'name' => (string) (config('links.setup.sqlite') ?: database_path('database.sqlite'))];
        }

        return ['driver' => 'mysql', 'host' => $db['host'], 'port' => (int) $db['port'], 'name' => $db['name'], 'user' => $db['user'], 'pass' => (string) ($db['pass'] ?? '')];
    }

    private function mailConfig(array $m): array
    {
        return [
            'host' => $m['host'], 'port' => (int) $m['port'], 'scheme' => $m['security'] === 'ssl' ? 'smtps' : null,
            'user' => (string) ($m['user'] ?? ''), 'pass' => (string) ($m['pass'] ?? ''), 'from' => $m['from'],
        ];
    }
}
