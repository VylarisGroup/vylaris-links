<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Updater\UpdateRunner;
use App\Support\Updater\Updater;
use App\Support\Updater\UpdaterException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class UpdaterTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    private const DOWNLOAD = 'https://github.com/VylarisGroup/vylaris-links/releases/download/v1.1.0/source-code.zip';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->tmp = sys_get_temp_dir().'/vl-upd-'.bin2hex(random_bytes(4));
        $this->writeFile('artisan', "#!/usr/bin/env php\nold");
        $this->writeFile('composer.json', '{}');
        $this->writeFile('VERSION', "1.0.0\n");
        $this->writeFile('app/Old.php', '<?php // alt');
        $this->writeFile('vendor/autoload.php', '<?php // alt');
        $this->writeFile('public/build/manifest.json', '{"alt":1}');
        $this->writeFile('public/index.php', '<?php // alt');
        $this->writeFile('.env', 'SECRET=1');
        $this->writeFile('storage/app/keep.txt', 'behalten');
        $this->writeFile('database/database.sqlite', 'olddb');
        $this->writeFile('bootstrap/cache/packages.php', '<?php // cache');

        config(['updater' => array_merge(config('updater'), [
            'base_path' => $this->tmp,
            'work_dir' => $this->tmp.'/storage/app/updater',
            'maintenance_mode' => false,
        ])]);
    }

    protected function tearDown(): void
    {
        $this->rm($this->tmp);
        parent::tearDown();
    }

    // ---------- Helfer ----------

    private function writeFile(string $rel, string $content): void
    {
        $f = $this->tmp.'/'.$rel;
        @mkdir(dirname($f), 0775, true);
        file_put_contents($f, $content);
    }

    private function rm(string $p): void
    {
        if (is_link($p) || is_file($p)) {
            @unlink($p);

            return;
        }
        if (is_dir($p)) {
            foreach (scandir($p) as $n) {
                if ($n !== '.' && $n !== '..') {
                    $this->rm("$p/$n");
                }
            }
            @rmdir($p);
        }
    }

    private function goodFiles(string $version = '1.1.0', string $prefix = ''): array
    {
        $f = [
            'artisan' => "#!/usr/bin/env php\nneu",
            'composer.json' => '{}',
            'VERSION' => $version."\n",
            'app/New.php' => '<?php // neu',
            'vendor/autoload.php' => '<?php // neu',
            'public/build/manifest.json' => '{"neu":1}',
            'public/index.php' => '<?php // neu',
            // Geschützte Pfade, die der Updater nie überschreiben darf:
            '.env' => 'HACKED=1',
            'storage/app/evil.txt' => 'böse',
            'database/database.sqlite' => 'evil',
            'bootstrap/cache/evil.php' => '<?php // böse',
        ];
        $out = [];
        foreach ($f as $k => $v) {
            $out[$prefix.$k] = $v;
        }

        return $out;
    }

    private function zip(array $files, array $symlinks = []): string
    {
        $path = sys_get_temp_dir().'/vl-zip-'.bin2hex(random_bytes(4)).'.zip';
        $za = new ZipArchive;
        $za->open($path, ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $za->addFromString($name, $content);
        }
        foreach ($symlinks as $name => $target) {
            $za->addFromString($name, $target);
            $za->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0120777 << 16);
        }
        $za->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private function fakeRelease(string $zipBytes, string $tag = 'v1.1.0', array $assetOverride = []): void
    {
        $asset = array_merge([
            'name' => 'source-code.zip',
            'size' => strlen($zipBytes),
            'digest' => 'sha256:'.hash('sha256', $zipBytes),
            'browser_download_url' => 'https://github.com/VylarisGroup/vylaris-links/releases/download/'.$tag.'/source-code.zip',
        ], $assetOverride);

        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => $tag, 'name' => 'Release '.$tag, 'body' => 'Neu: Dinge', 'draft' => false, 'prerelease' => false,
                'published_at' => '2026-10-23T08:00:00Z', 'html_url' => 'https://github.com/VylarisGroup/vylaris-links/releases/tag/'.$tag,
                'assets' => [$asset],
            ]),
            'github.com/*' => Http::response($zipBytes),
        ]);
    }

    private function runner(?\Closure $post = null): UpdateRunner
    {
        return new UpdateRunner(app(Updater::class), $post ?? fn () => null);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    // ---------- Prüfung ----------

    public function test_check_finds_newer_release(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles()));
        $s = app(Updater::class)->check();

        $this->assertNull($s['error']);
        $this->assertTrue($s['available']);
        $this->assertSame('1.1.0', $s['latest']['version']);
        $this->assertSame('1.0.0', $s['current']);
    }

    public function test_check_without_release_is_not_an_error(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);
        $s = app(Updater::class)->check();

        $this->assertNull($s['error']);
        $this->assertFalse($s['available']);
    }

    public function test_same_version_is_not_offered(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles('1.0.0')), 'v1.0.0');
        $this->assertFalse(app(Updater::class)->check()['available']);
    }

    public function test_missing_asset_is_reported(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles()), 'v1.1.0', ['name' => 'etwas-anderes.zip']);
        $this->assertStringContainsString('source-code.zip', app(Updater::class)->check()['error']);
    }

    public function test_asset_url_from_foreign_repo_is_refused(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles()), 'v1.1.0', ['browser_download_url' => 'https://github.com/jemand-anders/projekt/releases/download/v1.1.0/source-code.zip']);
        $this->assertStringContainsString('freigegebenen Repo', app(Updater::class)->check()['error']);

        $this->fakeRelease($this->zip($this->goodFiles()), 'v1.1.0', ['browser_download_url' => 'https://evil.example/source-code.zip']);
        $this->assertNotNull(app(Updater::class)->check()['error']);
    }

    public function test_untrusted_hosts_are_refused(): void
    {
        $u = app(Updater::class);
        $u->assertAllowedHost('https://objects.githubusercontent.com/x');
        foreach (['https://evil.example/x.zip', 'http://github.com/x', 'https://github.com.evil.example/x', 'ftp://github.com/x'] as $bad) {
            try {
                $u->assertAllowedHost($bad);
                $this->fail("Host hätte abgelehnt werden müssen: $bad");
            } catch (UpdaterException) {
                $this->assertTrue(true);
            }
        }
    }

    // ---------- Installation ----------

    public function test_install_replaces_files_and_keeps_protected_paths(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles()));

        $result = $this->runner()->run();

        $this->assertSame(['from' => '1.0.0', 'to' => '1.1.0'], $result);
        $this->assertSame('1.1.0', trim(file_get_contents("$this->tmp/VERSION")));
        $this->assertFileExists("$this->tmp/app/New.php");
        $this->assertFileDoesNotExist("$this->tmp/app/Old.php");
        $this->assertSame('{"neu":1}', file_get_contents("$this->tmp/public/build/manifest.json"));

        // Geschütztes bleibt unangetastet
        $this->assertSame('SECRET=1', file_get_contents("$this->tmp/.env"));
        $this->assertSame('behalten', file_get_contents("$this->tmp/storage/app/keep.txt"));
        $this->assertFileDoesNotExist("$this->tmp/storage/app/evil.txt");
        $this->assertSame('olddb', file_get_contents("$this->tmp/database/database.sqlite"));
        $this->assertFileExists("$this->tmp/bootstrap/cache/packages.php");
        $this->assertFileDoesNotExist("$this->tmp/bootstrap/cache/evil.php");

        // Backup mit der alten Version liegt bereit
        $this->assertNotEmpty(glob("$this->tmp/storage/app/updater/backups/*/files/app/Old.php"));

        $status = app(Updater::class)->readStatus();
        $this->assertSame('done', $status['state']);
        $this->assertSame(100, $status['percent']);
    }

    public function test_package_with_top_level_folder_is_supported(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles('1.1.0', 'vylaris-links-1.1.0/')));

        $this->runner()->run();

        $this->assertFileExists("$this->tmp/app/New.php");
        $this->assertSame('SECRET=1', file_get_contents("$this->tmp/.env"));
    }

    public function test_zip_slip_is_rejected_and_nothing_changes(): void
    {
        $files = $this->goodFiles() + ['../vl-evil-'.basename($this->tmp).'.txt' => 'böse'];
        $this->fakeRelease($this->zip($files));

        try {
            $this->runner()->run();
            $this->fail('Unsicheres Paket wurde akzeptiert.');
        } catch (UpdaterException $e) {
            $this->assertStringContainsString('unsicheren Pfad', $e->getMessage());
        }

        $this->assertFileExists("$this->tmp/app/Old.php");
        $this->assertFileDoesNotExist("$this->tmp/app/New.php");
        $this->assertFileDoesNotExist(dirname($this->tmp).'/vl-evil-'.basename($this->tmp).'.txt');
        $this->assertSame('failed', app(Updater::class)->readStatus()['state']);
    }

    public function test_symlink_entries_are_rejected(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles(), ['app/link' => '/etc/passwd']));

        $this->expectException(UpdaterException::class);
        $this->expectExceptionMessageMatches('/symbolische Links/');
        $this->runner()->run();
    }

    public function test_incomplete_package_is_rejected(): void
    {
        $files = $this->goodFiles();
        unset($files['vendor/autoload.php']);
        $this->fakeRelease($this->zip($files));

        try {
            $this->runner()->run();
            $this->fail('Unvollständiges Paket wurde akzeptiert.');
        } catch (UpdaterException $e) {
            $this->assertStringContainsString('vendor/autoload.php', $e->getMessage());
        }
        $this->assertFileExists("$this->tmp/app/Old.php");
    }

    public function test_version_mismatch_inside_package_is_rejected(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles('9.9.9')), 'v1.1.0');

        $this->expectException(UpdaterException::class);
        $this->expectExceptionMessageMatches('/passt nicht zum Release/');
        $this->runner()->run();
    }

    public function test_wrong_checksum_is_rejected(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles()), 'v1.1.0', ['digest' => 'sha256:'.str_repeat('0', 64)]);

        $this->expectException(UpdaterException::class);
        $this->expectExceptionMessageMatches('/Prüfsumme/');
        $this->runner()->run();
    }

    public function test_wrong_size_is_rejected(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles()), 'v1.1.0', ['size' => 12345, 'digest' => null]);

        $this->expectException(UpdaterException::class);
        $this->expectExceptionMessageMatches('/Grösse/');
        $this->runner()->run();
    }

    public function test_rollback_restores_everything_when_a_late_step_fails(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles()));

        try {
            $this->runner(fn () => throw new UpdaterException('Migration kaputt'))->run();
            $this->fail('Fehler wurde verschluckt.');
        } catch (UpdaterException $e) {
            $this->assertSame('Migration kaputt', $e->getMessage());
        }

        $this->assertSame('1.0.0', trim(file_get_contents("$this->tmp/VERSION")));
        $this->assertFileExists("$this->tmp/app/Old.php");
        $this->assertFileDoesNotExist("$this->tmp/app/New.php");
        $this->assertSame('{"alt":1}', file_get_contents("$this->tmp/public/build/manifest.json"));
        $this->assertSame('SECRET=1', file_get_contents("$this->tmp/.env"));
        $this->assertSame('olddb', file_get_contents("$this->tmp/database/database.sqlite"));

        $status = app(Updater::class)->readStatus();
        $this->assertSame('failed', $status['state']);
        $this->assertStringContainsString('Migration kaputt', $status['message']);
        $this->assertNotEmpty(array_filter($status['log'], fn ($l) => str_contains($l, 'Dateien wiederhergestellt')));
    }

    public function test_no_downgrade_without_force(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles('0.9.0')), 'v0.9.0');

        $this->expectException(UpdaterException::class);
        $this->expectExceptionMessageMatches('/neuesten Stand/');
        $this->runner()->run();
    }

    public function test_old_backups_are_pruned(): void
    {
        config(['updater.keep_backups' => 2]);
        foreach (['20250101-000000-v0.1.0', '20250102-000000-v0.2.0', '20250103-000000-v0.3.0'] as $b) {
            $this->writeFile("storage/app/updater/backups/$b/files/x.txt", 'x');
        }
        $this->fakeRelease($this->zip($this->goodFiles()));

        $this->runner()->run();

        $this->assertCount(2, app(Updater::class)->backups());
    }

    public function test_disabled_updater_does_nothing(): void
    {
        config(['updater.enabled' => false]);
        $this->expectException(UpdaterException::class);
        $this->runner()->run();
    }

    // ---------- Admin-Bereich ----------

    public function test_update_pages_require_admin(): void
    {
        $normal = User::factory()->create();

        $this->get('/admin/update')->assertRedirect('/anmelden');
        $this->actingAs($normal)->get('/admin/update')->assertForbidden();
        $this->actingAs($normal)->post('/admin/update/pruefen')->assertForbidden();
        $this->actingAs($normal)->post('/admin/update/installieren')->assertForbidden();
        $this->actingAs($normal)->get('/admin/update/status')->assertForbidden();
    }

    public function test_admin_sees_update_page_and_can_check(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles()));
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/update/pruefen')->assertRedirect();
        $this->actingAs($admin)->get('/admin/update')->assertOk()
            ->assertSee('v1.0.0')->assertSee('v1.1.0')->assertSee('Update installieren');
        $this->actingAs($admin)->getJson('/admin/update/status')->assertOk()->assertJsonPath('state', 'idle');
    }

    public function test_install_endpoint_refuses_when_already_current(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles('1.0.0')), 'v1.0.0');

        $this->actingAs($this->admin())->postJson('/admin/update/installieren')->assertStatus(422);
        $this->assertFileExists("$this->tmp/app/Old.php");
    }

    public function test_install_endpoint_refuses_parallel_runs(): void
    {
        $this->fakeRelease($this->zip($this->goodFiles()));
        app(Updater::class)->writeStatus(['state' => 'running', 'step' => 'swap', 'message' => 'läuft', 'percent' => 70, 'log' => []]);

        $this->actingAs($this->admin())->postJson('/admin/update/installieren')->assertStatus(409);
    }
}
