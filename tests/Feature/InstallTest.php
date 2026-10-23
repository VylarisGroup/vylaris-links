<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\EnvFile;
use Tests\TestCase;

class InstallTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/links-env-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
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

    public function test_env_file_sets_and_keeps_other_lines(): void
    {
        $path = $this->dir.'/.env';
        file_put_contents($path, "# Kommentar\nAPP_NAME=Test\nDB_HOST=old\n");
        $env = new EnvFile($path);

        $env->setMany(['DB_HOST' => 'db.example.ch', 'NEW_KEY' => 'a b']);

        $this->assertSame("# Kommentar\nAPP_NAME=Test\nDB_HOST=db.example.ch\nNEW_KEY=\"a b\"\n", file_get_contents($path));
        $this->assertSame('db.example.ch', $env->get('DB_HOST'));
        $this->assertSame('a b', $env->get('NEW_KEY'));
        $this->assertNull($env->get('MISSING'));
    }

    public function test_env_file_escapes_special_characters_and_rejects_newlines(): void
    {
        $env = new EnvFile($this->dir.'/.env');
        $env->set('DB_PASSWORD', 'p"a$s\\s #1');
        $this->assertSame('p"a$s\\s #1', $env->get('DB_PASSWORD'));

        $this->expectException(\InvalidArgumentException::class);
        $env->set('X', "a\nB=1");
    }

    public function test_env_file_rejects_invalid_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EnvFile($this->dir.'/.env'))->set('bad key', '1');
    }

    public function test_install_creates_env_key_and_admin_without_prompts(): void
    {
        $envPath = $this->dir.'/.env';
        $this->app->useEnvironmentPath($this->dir);
        $db = $this->dir.'/db.sqlite';

        $this->artisan('links:install', [
            '--url' => 'https://links.example.ch/',
            '--env' => 'local',
            '--db-driver' => 'sqlite',
            '--db-name' => $db,
            '--admin-email' => 'chef@example.ch',
            '--admin-password' => 'Geheim12345',
            '--no-optimize' => true,
            '--no-interaction' => true,
        ])->run();

        $env = new EnvFile($envPath);
        $this->assertSame('https://links.example.ch', $env->get('APP_URL'));
        $this->assertStringStartsWith('base64:', (string) $env->get('APP_KEY'));
        $this->assertSame('sqlite', $env->get('DB_CONNECTION'));
        $this->assertTrue(is_file($db));
        $admin = User::where('email', 'chef@example.ch')->first();
        $this->assertNotNull($admin);
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertNotSame('Geheim12345', $admin->password);
    }
}
