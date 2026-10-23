<?php

namespace App\Support\Updater;

use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Installiert ein Release aus der source-code.zip:
 * Download → Prüfung → Entpacken → Backup → Wartungsmodus → Austausch → Migration → Rollback bei Fehlern.
 */
class UpdateRunner
{
    /** @var array<int, array{0: string, 1: string}> Journal der Datei-Änderungen: [Art, relativer Pfad] */
    private array $journal = [];

    private array $log = [];

    private array $meta = [];

    private bool $weDownMaintenance = false;

    private ?int $batchBefore = null;

    private ?string $backupFiles = null;

    /** @param Closure|null $postSwap ersetzt Migration/Cache-Leeren (für Tests) */
    public function __construct(private Updater $updater, private ?Closure $postSwap = null, private ?Closure $output = null)
    {
    }

    public function onStep(?Closure $output): static
    {
        $this->output = $output;

        return $this;
    }

    /** @throws UpdaterException */
    public function run(bool $force = false): array
    {
        $u = $this->updater;
        if (! $u->enabled()) {
            throw new UpdaterException('Das Update-System ist abgeschaltet (UPDATER_ENABLED).');
        }

        $lock = Cache::lock('links-updater', 3600);
        if (! $lock->get()) {
            throw new UpdaterException('Es läuft bereits ein Update.');
        }

        $this->journal = [];
        $this->log = [];
        $this->weDownMaintenance = false;
        $this->batchBefore = null;
        $run = null;

        try {
            $this->meta = ['from' => $u->currentVersion(), 'started_at' => now()->toIso8601String()];
            $this->step('prepare', 'Prüfe Voraussetzungen …', 3);
            foreach ($u->requirements() as $r) {
                if ($r['blocking'] && ! $r['ok']) {
                    throw new UpdaterException('Voraussetzung nicht erfüllt: '.$r['label']);
                }
            }

            $this->step('check', 'Suche neues Release …', 8);
            $state = $u->check();
            if ($state['error']) {
                throw new UpdaterException($state['error']);
            }
            $release = Release::fromArray($state['latest'] ?? null);
            if (! $release) {
                throw new UpdaterException('Es ist noch kein Release veröffentlicht.');
            }
            $this->meta['to'] = $release->version;
            $current = $u->currentVersion();
            if (! $force && ! version_compare($release->version, $current, '>')) {
                throw new UpdaterException("Du bist bereits auf dem neuesten Stand (Version {$current}).");
            }

            $run = $u->workDir('runs/'.date('Ymd-His').'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $release->version));
            $this->mkdir($run);

            $this->step('download', "Lade {$release->tag} herunter …", 15);
            $zip = $run.'/source-code.zip';
            $this->download($release, $zip);

            $this->step('verify', 'Prüfe das Paket …', 30);
            $info = $this->inspectZip($zip);

            $this->step('extract', 'Entpacke das Paket …', 42);
            $stage = $run.'/stage';
            $this->extract($zip, $info, $stage);
            $this->verifyPackage($stage, $release);

            $this->step('backup', 'Sichere die Datenbank …', 55);
            $backupDir = $u->workDir('backups/'.date('Ymd-His').'-v'.preg_replace('/[^A-Za-z0-9._-]/', '_', $current));
            $this->backupFiles = $backupDir.'/files';
            $this->mkdir($this->backupFiles);
            if ($dump = (new DatabaseBackup)->dump($backupDir)) {
                $this->note('Datenbank gesichert: '.basename($dump));
            } else {
                $this->note('Hinweis: Für diesen Datenbanktyp gibt es keine automatische Sicherung.');
            }

            $this->step('maintenance', 'Schalte den Wartungsmodus ein …', 62);
            $this->maintenanceOn();
            $this->batchBefore = $this->maxBatch();

            $this->step('swap', 'Tausche die Dateien aus …', 70);
            $this->swap($stage);

            $this->step('migrate', 'Aktualisiere die Datenbank …', 85);
            $this->runPostSwap();

            $this->step('finish', 'Schliesse das Update ab …', 95);
            $this->maintenanceOff();
            $this->pruneBackups();
            $this->deleteTree($run);
            Cache::forget(Updater::CACHE_KEY);

            $this->meta['finished_at'] = now()->toIso8601String();
            $this->write('done', 'finish', "Update auf Version {$release->version} abgeschlossen.", 100);

            return ['from' => $current, 'to' => $release->version];
        } catch (Throwable $e) {
            $this->fail($e);
            $this->deleteTree($run);
            throw $e instanceof UpdaterException ? $e : new UpdaterException($e->getMessage(), 0, $e);
        } finally {
            $lock->release();
        }
    }

    // ------------------------------------------------------------------ Download & Prüfung

    private function download(Release $release, string $dest): void
    {
        $u = $this->updater;
        $u->assertAllowedHost($release->assetUrl);

        $res = $u->http('application/octet-stream')->withOptions([
            'sink' => $dest,
            'allow_redirects' => [
                'max' => 5,
                'protocols' => ['https'],
                'on_redirect' => function ($request, $response, $uri) use ($u) {
                    $u->assertAllowedHost((string) $uri);
                },
            ],
        ])->timeout(300)->get($release->assetUrl);

        if (! $res->successful() || ! is_file($dest)) {
            throw new UpdaterException('Der Download ist fehlgeschlagen (Status '.$res->status().').');
        }

        $size = filesize($dest);
        if ($size > $u->config('max_zip_mb', 200) * 1048576) {
            throw new UpdaterException('Das Paket ist grösser als erlaubt.');
        }
        if ($release->assetSize > 0 && $size !== $release->assetSize) {
            throw new UpdaterException('Die heruntergeladene Datei hat nicht die erwartete Grösse.');
        }
        if ($release->digest && str_starts_with($release->digest, 'sha256:')) {
            if (! hash_equals(substr($release->digest, 7), hash_file('sha256', $dest))) {
                throw new UpdaterException('Die Prüfsumme der heruntergeladenen Datei stimmt nicht überein.');
            }
            $this->note('Prüfsumme (SHA-256) stimmt.');
        }
    }

    /** Prüft jeden Eintrag der Zip-Datei und gibt Einträge samt Wurzelordner zurück. */
    private function inspectZip(string $file): array
    {
        $za = new ZipArchive;
        if ($za->open($file, ZipArchive::CHECKCONS) !== true) {
            throw new UpdaterException('Das Paket ist keine gültige Zip-Datei.');
        }

        try {
            $count = $za->numFiles;
            if ($count === 0 || $count > $this->updater->config('max_files', 60000)) {
                throw new UpdaterException('Das Paket hat eine ungültige Anzahl an Dateien.');
            }

            $limit = $this->updater->config('max_unzipped_mb', 600) * 1048576;
            $total = 0;
            $entries = [];
            for ($i = 0; $i < $count; $i++) {
                $st = $za->statIndex($i);
                $name = (string) $st['name'];

                if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)
                    || in_array('..', explode('/', $name), true)) {
                    throw new UpdaterException('Das Paket enthält einen unsicheren Pfad und wird abgelehnt.');
                }

                if ($za->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX && ((($attr >> 16) & 0170000) === 0120000)) {
                    throw new UpdaterException('Das Paket enthält symbolische Links und wird abgelehnt.');
                }

                $total += (int) $st['size'];
                if ($total > $limit) {
                    throw new UpdaterException('Das entpackte Paket wäre grösser als erlaubt.');
                }
                $entries[] = ['name' => $name, 'dir' => str_ends_with($name, '/')];
            }
        } finally {
            $za->close();
        }

        $names = array_column($entries, 'name');
        if (in_array('artisan', $names, true)) {
            $prefix = '';
        } else {
            $prefix = null;
            foreach ($names as $n) {
                if (preg_match('#^([^/]+)/artisan$#', $n, $m)) {
                    $prefix = $m[1].'/';
                    break;
                }
            }
            if ($prefix === null) {
                throw new UpdaterException('Das Paket enthält keine Laravel-Anwendung (artisan fehlt).');
            }
        }

        return ['entries' => $entries, 'prefix' => $prefix];
    }

    private function extract(string $file, array $info, string $stage): void
    {
        $this->mkdir($stage);
        $za = new ZipArchive;
        $za->open($file);

        try {
            $prefix = $info['prefix'];
            foreach ($info['entries'] as $e) {
                if ($prefix !== '' && ! str_starts_with($e['name'], $prefix)) {
                    continue;
                }
                $rel = rtrim(substr($e['name'], strlen($prefix)), '/');
                if ($rel === '' || $this->isProtected($rel)) {
                    continue;
                }

                $target = $stage.'/'.$rel;
                if ($e['dir']) {
                    $this->mkdir($target);

                    continue;
                }

                $this->mkdir(dirname($target));
                $in = $za->getStream($e['name']);
                $out = $in ? fopen($target, 'wb') : false;
                if (! $in || ! $out) {
                    throw new UpdaterException('Eine Datei konnte nicht entpackt werden: '.$rel);
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                if ($rel === 'artisan') {
                    @chmod($target, 0755);
                }
            }
        } finally {
            $za->close();
        }
    }

    private function verifyPackage(string $stage, Release $release): void
    {
        foreach (['artisan', 'composer.json', 'VERSION', 'app', 'vendor/autoload.php', 'public/build/manifest.json'] as $need) {
            if (! file_exists("$stage/$need")) {
                throw new UpdaterException("Das Paket ist unvollständig: {$need} fehlt (erwartet wird ein fertiger Stand mit vendor/ und public/build).");
            }
        }
        $v = Updater::normalize((string) file_get_contents("$stage/VERSION"));
        if ($v !== $release->version) {
            throw new UpdaterException("Die Version im Paket ({$v}) passt nicht zum Release ({$release->version}).");
        }
    }

    // ------------------------------------------------------------------ Austausch

    private function swap(string $stage): void
    {
        foreach (scandir($stage) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $this->swapPath($stage, $name);
        }
    }

    private function swapPath(string $stage, string $rel): void
    {
        if ($this->isProtected($rel)) {
            return;
        }
        $source = $stage.'/'.$rel;
        $target = $this->updater->basePath($rel);

        if (is_dir($source) && ! is_link($source) && in_array($rel, $this->updater->config('merge_dirs', []), true)) {
            $this->mkdir($target);
            foreach (scandir($source) as $child) {
                if ($child !== '.' && $child !== '..') {
                    $this->swapPath($stage, $rel.'/'.$child);
                }
            }

            return;
        }

        $existed = file_exists($target) || is_link($target);
        if ($existed) {
            $backup = $this->backupFiles.'/'.$rel;
            $this->mkdir(dirname($backup));
            $this->move($target, $backup);
            $this->journal[] = ['replaced', $rel];
        }
        $this->mkdir(dirname($target));
        try {
            $this->move($source, $target);
        } catch (Throwable $e) {
            if ($existed) {
                $this->move($this->backupFiles.'/'.$rel, $target);
                array_pop($this->journal);
            }
            throw $e;
        }
        if (! $existed) {
            $this->journal[] = ['added', $rel];
        }
    }

    private function rollbackFiles(): void
    {
        foreach (array_reverse($this->journal) as [$kind, $rel]) {
            $target = $this->updater->basePath($rel);
            $this->deleteTree($target);
            if ($kind === 'replaced') {
                $this->mkdir(dirname($target));
                $this->move($this->backupFiles.'/'.$rel, $target);
            }
        }
        $this->journal = [];
    }

    private function isProtected(string $rel): bool
    {
        $rel = trim(str_replace('\\', '/', $rel), '/');
        foreach ($this->updater->config('protected', []) as $p) {
            if ($rel === $p || str_starts_with($rel, $p.'/') || ($p !== '' && str_contains($p, '*') && fnmatch($p, $rel))) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ Datenbank, Wartung, Nacharbeiten

    private function runPostSwap(): void
    {
        if ($this->postSwap) {
            ($this->postSwap)();

            return;
        }

        $php = (new PhpExecutableFinder)->find(false);
        $base = $this->updater->basePath();

        if ($php && function_exists('proc_open') && DIRECTORY_SEPARATOR === '/') {
            foreach ([['migrate', '--force', '--no-interaction'], ['optimize:clear'], ['storage:link']] as $cmd) {
                $p = new Process([$php, $base.'/artisan', ...$cmd], $base, null, null, 300);
                $p->run();
                if (! $p->isSuccessful() && $cmd[0] !== 'storage:link') {
                    $msg = trim($p->getErrorOutput() ?: $p->getOutput());
                    throw new UpdaterException("Der Schritt „{$cmd[0]}“ ist fehlgeschlagen: ".mb_substr($msg, 0, 400));
                }
                $this->note('artisan '.$cmd[0].' ausgeführt.');
            }
            $health = new Process([$php, $base.'/artisan', '--version'], $base, null, null, 60);
            $health->run();
            if (! $health->isSuccessful()) {
                throw new UpdaterException('Die neue Version startet nicht (Kontrolle fehlgeschlagen).');
            }

            return;
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('optimize:clear');
        $this->note('Migrationen im Webrequest ausgeführt.');
    }

    private function maxBatch(): int
    {
        try {
            return Schema::hasTable('migrations') ? (int) DB::table('migrations')->max('batch') : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private function maintenanceOn(): void
    {
        if (! $this->updater->config('maintenance_mode', true) || app()->isDownForMaintenance()) {
            return;
        }
        Artisan::call('down', ['--retry' => 60]);
        $this->weDownMaintenance = true;
    }

    private function maintenanceOff(): void
    {
        if ($this->weDownMaintenance) {
            Artisan::call('up');
            $this->weDownMaintenance = false;
        }
    }

    private function pruneBackups(): void
    {
        $dir = $this->updater->workDir('backups');
        if (! is_dir($dir)) {
            return;
        }
        $names = array_values(array_filter(scandir($dir), fn ($n) => $n[0] !== '.' && is_dir("$dir/$n")));
        rsort($names);
        foreach (array_slice($names, max(1, (int) $this->updater->config('keep_backups', 3))) as $old) {
            $this->deleteTree("$dir/$old");
        }
    }

    // ------------------------------------------------------------------ Fehlerbehandlung

    private function fail(Throwable $e): void
    {
        $msg = $e->getMessage();
        $this->note('Fehler: '.$msg);

        if ($this->journal || $this->batchBefore !== null) {
            $this->note('Setze auf die vorherige Version zurück …');
            try {
                // Zuerst Migrationen zurücknehmen (solange die neuen Migrationsdateien noch da sind)
                $after = $this->maxBatch();
                if ($this->batchBefore !== null && $after > $this->batchBefore && $this->journal && ! $this->postSwap) {
                    $php = (new PhpExecutableFinder)->find(false);
                    if ($php) {
                        $p = new Process([$php, $this->updater->basePath('artisan'), 'migrate:rollback', '--force', '--batch='.$after], $this->updater->basePath(), null, null, 300);
                        $p->run();
                        $this->note($p->isSuccessful() ? 'Datenbank-Änderungen zurückgenommen.' : 'Datenbank-Rollback nicht möglich – Sicherung manuell einspielen.');
                    }
                }
                $this->rollbackFiles();
                $this->note('Dateien wiederhergestellt.');
            } catch (Throwable $r) {
                $this->note('Rollback unvollständig: '.$r->getMessage().' – Sicherung liegt in '.$this->updater->workDir('backups'));
            }
        }

        try {
            $this->maintenanceOff();
        } catch (Throwable) {
        }

        $this->meta['finished_at'] = now()->toIso8601String();
        $this->write('failed', 'failed', 'Update fehlgeschlagen: '.$msg, null);
    }

    // ------------------------------------------------------------------ Hilfsfunktionen

    private function step(string $step, string $message, int $percent): void
    {
        $this->note($message);
        $this->write('running', $step, $message, $percent);
    }

    private function note(string $line): void
    {
        $this->log[] = '['.now()->format('H:i:s').'] '.$line;
        if ($this->output) {
            ($this->output)($line);
        }
    }

    private function write(string $state, string $step, string $message, ?int $percent): void
    {
        $this->updater->writeStatus($this->meta + [
            'state' => $state,
            'step' => $step,
            'message' => $message,
            'percent' => $percent,
            'log' => array_slice($this->log, -60),
        ]);
    }

    private function mkdir(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new UpdaterException('Ordner konnte nicht angelegt werden: '.basename($dir));
        }
    }

    private function move(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }
        // Rename schlägt über Dateisystem-Grenzen fehl → kopieren und löschen
        $this->copyTree($from, $to);
        $this->deleteTree($from);
    }

    private function copyTree(string $from, string $to): void
    {
        if (is_dir($from) && ! is_link($from)) {
            $this->mkdir($to);
            foreach (scandir($from) as $n) {
                if ($n !== '.' && $n !== '..') {
                    $this->copyTree("$from/$n", "$to/$n");
                }
            }

            return;
        }
        if (! @copy($from, $to)) {
            throw new UpdaterException('Datei konnte nicht kopiert werden: '.basename($from));
        }
    }

    private function deleteTree(?string $path): void
    {
        if ($path === null || $path === '' || (! file_exists($path) && ! is_link($path))) {
            return;
        }
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }
        foreach (scandir($path) as $n) {
            if ($n !== '.' && $n !== '..') {
                $this->deleteTree("$path/$n");
            }
        }
        @rmdir($path);
    }
}
