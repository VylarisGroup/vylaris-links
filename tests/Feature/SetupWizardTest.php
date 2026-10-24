<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\EnvFile;
use App\Support\Install\SetupState;
use Tests\TestCase;

/** Der Einrichtungsassistent läuft ohne Session/DB und wird hier gegen temporäre Ordner geprüft. */
class SetupWizardTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/links-setup-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->app->useEnvironmentPath($this->dir);
        config([
            'links.setup.enabled' => true,
            'links.setup.dir' => $this->dir,
            'links.setup.sqlite' => $this->dir.'/db.sqlite',
            'links.setup.token' => null,
        ]);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->dir) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                @unlink($this->dir.'/'.$f);
            }
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'instance' => ['name' => 'Meine Links', 'url' => 'https://links.example.ch', 'env' => 'local', 'registration' => false, 'max_pages' => 5, 'updates' => true],
            'db' => ['driver' => 'sqlite'],
            'admin' => ['name' => 'Chef', 'email' => 'chef@example.ch', 'password' => 'Geheim12345', 'password_confirmation' => 'Geheim12345'],
            'mail' => ['mode' => 'log'],
        ], $override);
    }

    private function token(): string
    {
        $this->get('/setup')->assertOk();

        return trim((string) file_get_contents($this->dir.'/setup-token'));
    }

    private function api(string $path, array $data, ?string $token)
    {
        return $this->postJson('/setup/api/'.$path, $data, $token === null ? [] : ['X-Setup-Token' => $token]);
    }

    public function test_every_page_redirects_to_setup_until_installed(): void
    {
        $this->get('/')->assertRedirect('/setup');
        $this->get('/anmelden')->assertRedirect('/setup');
        $this->get('/irgendein-profil')->assertRedirect('/setup');
    }

    public function test_setup_page_creates_token_and_sends_security_headers(): void
    {
        $r = $this->get('/setup')->assertOk()->assertSee('Einrichtungs-Token')->assertDontSee('<script>alert', false);
        $this->assertSame('DENY', $r->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $r->headers->get('Content-Security-Policy'));
        $this->assertMatchesRegularExpression('/^[a-z0-9]{5}(-[a-z0-9]{5}){3}$/', $this->token());
        $this->assertEmpty($r->headers->getCookies(), 'Der Assistent darf keine Cookies setzen.');
    }

    public function test_api_requires_the_token(): void
    {
        $token = $this->token();

        $this->api('start', [], null)->assertForbidden();
        $this->api('start', [], 'falsch')->assertForbidden();
        $this->api('datenbank', ['db' => ['driver' => 'sqlite']], 'falsch')->assertForbidden();
        $this->api('installieren', $this->payload(), null)->assertForbidden();
        $this->assertFalse(is_file($this->dir.'/installed.json'));

        $r = $this->api('start', [], strtoupper($token))->assertOk();
        $r->assertJsonStructure(['requirements' => [['label', 'state', 'hint']], 'blocking', 'defaults' => ['instance', 'db', 'mail']]);
    }

    public function test_fixed_token_from_config_works(): void
    {
        config(['links.setup.token' => 'mein-geheimes-token']);
        $this->api('start', [], 'meinGeheimesToken')->assertOk();
        $this->api('start', [], 'nein')->assertForbidden();
    }

    public function test_installed_instance_locks_the_wizard(): void
    {
        file_put_contents($this->dir.'/installed.json', '{}');

        $this->get('/setup')->assertRedirect('/');
        $this->get('/anmelden')->assertOk();
        $this->api('start', [], 'egal')->assertForbidden();
        $this->api('installieren', $this->payload(), 'egal')->assertForbidden();
    }

    public function test_install_validates_input(): void
    {
        $token = $this->token();

        $this->api('installieren', $this->payload(['admin' => ['password' => 'kurz', 'password_confirmation' => 'kurz']]), $token)
            ->assertUnprocessable()->assertJsonValidationErrors(['admin.password']);
        $this->api('installieren', $this->payload(['admin' => ['password_confirmation' => 'anders12345']]), $token)
            ->assertUnprocessable()->assertJsonValidationErrors(['admin.password']);
        $this->api('installieren', $this->payload(['instance' => ['url' => 'javascript:alert(1)']]), $token)
            ->assertUnprocessable()->assertJsonValidationErrors(['instance.url']);
        $this->api('installieren', $this->payload(['db' => ['driver' => 'mysql', 'host' => 'h', 'port' => 3306, 'name' => 'a;drop', 'user' => 'u']]), $token)
            ->assertUnprocessable()->assertJsonValidationErrors(['db.name']);
        $this->api('installieren', $this->payload(['instance' => ['name' => "Evil\nAPP_DEBUG=true"]]), $token)->assertUnprocessable();
        $this->api('installieren', $this->payload(['mail' => ['mode' => 'smtp', 'host' => 'smtp.example.ch', 'port' => 587, 'security' => 'starttls', 'from' => 'kein-mail']]), $token)
            ->assertUnprocessable()->assertJsonValidationErrors(['mail.from']);

        $this->assertFalse(is_file($this->dir.'/.env'), 'Bei ungültigen Eingaben darf nichts geschrieben werden.');
    }

    public function test_database_probe(): void
    {
        $token = $this->token();

        $this->api('datenbank', ['db' => ['driver' => 'sqlite']], $token)->assertOk()->assertJson(['ok' => true]);
        $this->api('datenbank', ['db' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'name' => 'x', 'user' => 'u', 'pass' => 'p']], $token)
            ->assertOk()->assertJson(['ok' => false]);
    }

    public function test_full_install_with_sqlite(): void
    {
        $token = $this->token();

        $r = $this->api('installieren', $this->payload(), $token)->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('https://links.example.ch/anmelden', $r->json('login'));
        $this->assertSame([], array_values(array_filter($r->json('steps'), fn ($s) => $s['state'] === 'fail')));

        $env = new EnvFile($this->dir.'/.env');
        $this->assertSame('Meine Links', $env->get('APP_NAME'));
        $this->assertSame('https://links.example.ch', $env->get('APP_URL'));
        $this->assertSame('closed', $env->get('LINKS_REGISTRATION'));
        $this->assertSame('5', $env->get('LINKS_MAX_PAGES'));
        $this->assertSame('log', $env->get('MAIL_MAILER'));
        $this->assertStringStartsWith('base64:', (string) $env->get('APP_KEY'));
        $this->assertSame('sqlite', $env->get('DB_CONNECTION'));

        $admin = User::where('email', 'chef@example.ch')->first();
        $this->assertNotNull($admin);
        $this->assertTrue((bool) $admin->is_admin);

        $this->assertTrue(is_file($this->dir.'/installed.json'));
        $this->assertFalse(is_file($this->dir.'/setup-token'), 'Das Token muss nach der Einrichtung gelöscht sein.');

        // gesperrt
        $this->get('/setup')->assertRedirect('/');
        $this->api('installieren', $this->payload(), $token)->assertForbidden();
    }

    public function test_registration_can_be_closed(): void
    {
        file_put_contents($this->dir.'/installed.json', '{}');
        config(['links.registration_open' => false]);

        $this->get('/registrieren')->assertRedirect('/anmelden');
        $this->get('/anmelden')->assertOk()->assertDontSee('Konto erstellen');

        config(['links.registration_open' => true]);
        $this->get('/registrieren')->assertOk();
    }

    public function test_existing_instance_with_admin_is_detected(): void
    {
        $this->assertFalse(SetupState::isInstalled());
    }

    public function test_errors_name_their_cause_only_before_setup_is_done(): void
    {
        \Illuminate\Support\Facades\Route::get('/setup/boom', fn () => throw new \RuntimeException('kaputt-xyz'));

        $this->get('/setup/boom')->assertStatus(500)->assertSee('RuntimeException: kaputt-xyz', false);
        $this->getJson('/setup/boom')->assertStatus(500)->assertJsonPath('message', fn ($m) => str_contains($m, 'kaputt-xyz'));

        file_put_contents($this->dir.'/installed.json', '{}');
        config(['app.debug' => false]); // wie in Produktion
        $this->get('/setup/boom')->assertStatus(500)->assertDontSee('kaputt-xyz', false);
    }
}
