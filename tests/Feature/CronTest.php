<?php

namespace Tests\Feature;

use App\Support\Cron;
use Tests\TestCase;

class CronTest extends TestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = str_repeat('aB3', 14); // 42 Zeichen
        config(['links.cron.token' => $this->token]);
        @unlink(Cron::lastRunPath());
    }

    protected function tearDown(): void
    {
        @unlink(Cron::lastRunPath());
        parent::tearDown();
    }

    public function test_correct_token_runs_the_scheduler(): void
    {
        $this->assertNull(Cron::lastRun());

        $r = $this->get('/cron/'.$this->token)->assertOk()->assertJson(['ok' => true]);
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
        $this->assertEmpty($r->headers->getCookies(), 'Die Cron-Adresse darf keine Cookies setzen.');
        $this->assertEqualsWithDelta(time(), Cron::lastRun(), 5);
    }

    public function test_head_and_post_also_work(): void
    {
        $this->post('/cron/'.$this->token)->assertOk();
        $this->call('HEAD', '/cron/'.$this->token)->assertOk();
    }

    public function test_wrong_or_missing_token_looks_like_an_unknown_page(): void
    {
        $this->get('/cron/'.str_repeat('x', 42))->assertNotFound();
        $this->get('/cron/kurz')->assertNotFound();
        $this->assertNull(Cron::lastRun());

        config(['links.cron.token' => null]);
        $this->get('/cron/'.$this->token)->assertNotFound();

        config(['links.cron.token' => 'zukurz']);
        $this->get('/cron/zukurz')->assertNotFound();
    }

    public function test_url_helper(): void
    {
        $this->assertSame('https://x.ch/cron/'.$this->token, Cron::url('https://x.ch/'));
        config(['links.cron.token' => null]);
        $this->assertNull(Cron::url('https://x.ch'));
    }

    public function test_running_scheduler_is_skipped_while_locked(): void
    {
        $lock = fopen(storage_path('framework/cron.lock'), 'c');
        flock($lock, LOCK_EX);

        $this->get('/cron/'.$this->token)->assertOk()->assertJsonPath('skipped', fn ($v) => $v !== null);
        $this->assertNull(Cron::lastRun());

        flock($lock, LOCK_UN);
        fclose($lock);
    }

    public function test_cron_token_command_creates_and_regenerates(): void
    {
        $dir = sys_get_temp_dir().'/links-cron-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $this->app->useEnvironmentPath($dir);
        config(['app.env' => 'local']);

        $this->artisan('links:cron-token')->assertSuccessful();
        $first = (new \App\Support\EnvFile($dir.'/.env'))->get('LINKS_CRON_TOKEN');
        $this->assertSame(40, strlen((string) $first));

        $this->artisan('links:cron-token')->assertSuccessful();
        $this->assertSame($first, (new \App\Support\EnvFile($dir.'/.env'))->get('LINKS_CRON_TOKEN'));

        $this->artisan('links:cron-token', ['--regenerate' => true])->assertSuccessful();
        $this->assertNotSame($first, (new \App\Support\EnvFile($dir.'/.env'))->get('LINKS_CRON_TOKEN'));

        @unlink($dir.'/.env');
        @rmdir($dir);
    }
}
