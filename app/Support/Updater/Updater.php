<?php

namespace App\Support\Updater;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Prüft GitHub-Releases auf neue Versionen und liefert Zustand, Status und Voraussetzungen.
 * Die eigentliche Installation steckt in UpdateRunner.
 */
class Updater
{
    public const CACHE_KEY = 'updater.check';

    public function __construct(private array $cfg)
    {
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->cfg[$key] ?? $default;
    }

    public function enabled(): bool
    {
        return (bool) ($this->cfg['enabled'] ?? true);
    }

    public function basePath(string $sub = ''): string
    {
        $base = rtrim($this->cfg['base_path'] ?: base_path(), '/\\');

        return $sub === '' ? $base : $base.'/'.ltrim($sub, '/');
    }

    public function workDir(string $sub = ''): string
    {
        $dir = rtrim($this->cfg['work_dir'], '/\\');

        return $sub === '' ? $dir : $dir.'/'.ltrim($sub, '/');
    }

    public static function normalize(string $v): string
    {
        return ltrim(trim($v), 'vV');
    }

    public function currentVersion(): string
    {
        $file = $this->basePath('VERSION');
        $v = is_file($file) ? self::normalize((string) file_get_contents($file)) : '';

        return $v !== '' ? $v : self::normalize((string) ($this->cfg['version'] ?? '0.0.0'));
    }

    public function http(string $accept = 'application/vnd.github+json'): PendingRequest
    {
        $req = Http::withHeaders([
            'Accept' => $accept,
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'VylarisLinks-Updater',
        ])->timeout(20);

        if (! empty($this->cfg['token'])) {
            $req = $req->withToken($this->cfg['token']);
        }

        return $req;
    }

    public function assertAllowedHost(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || ! in_array($host, $this->cfg['allowed_hosts'], true)) {
            throw new UpdaterException('Der Download-Adresse wird nicht vertraut ('.($host ?: 'ungültig').').');
        }
    }

    /** Holt das neueste veröffentlichte Release. Gibt null zurück, wenn es noch keins gibt. */
    public function fetchLatest(): ?Release
    {
        $repo = (string) $this->cfg['repo'];
        if (! preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
            throw new UpdaterException('UPDATER_REPO ist ungültig (erwartet: besitzer/repo).');
        }

        try {
            $res = $this->http()->get(rtrim($this->cfg['api'], '/')."/repos/{$repo}/releases/latest");
        } catch (\Throwable $e) {
            throw new UpdaterException('GitHub ist gerade nicht erreichbar.', 0, $e);
        }

        if ($res->status() === 404) {
            return null;
        }
        if (! $res->successful()) {
            $hint = in_array($res->status(), [403, 429], true) ? ' (Abfrage-Limit erreicht, bitte später erneut versuchen)' : '';
            throw new UpdaterException('GitHub antwortete mit Status '.$res->status().$hint.'.');
        }

        $data = $res->json();
        if (! is_array($data) || empty($data['tag_name']) || ! empty($data['draft']) || ! empty($data['prerelease'])) {
            return null;
        }

        $asset = collect($data['assets'] ?? [])->firstWhere('name', $this->cfg['asset']);
        if (! $asset) {
            throw new UpdaterException('Das Release '.$data['tag_name'].' enthält keine Datei „'.$this->cfg['asset'].'“.');
        }

        $url = (string) ($asset['browser_download_url'] ?? '');
        $expected = 'https://github.com/'.strtolower($repo).'/releases/download/';
        if (! str_starts_with(strtolower($url), $expected)) {
            throw new UpdaterException('Die Download-Adresse des Releases gehört nicht zum freigegebenen Repo.');
        }

        return new Release(
            tag: (string) $data['tag_name'],
            version: self::normalize((string) $data['tag_name']),
            name: (string) ($data['name'] ?: $data['tag_name']),
            notes: (string) ($data['body'] ?? ''),
            publishedAt: $data['published_at'] ?? null,
            url: (string) ($data['html_url'] ?? ''),
            assetUrl: $url,
            assetSize: (int) ($asset['size'] ?? 0),
            digest: isset($asset['digest']) ? (string) $asset['digest'] : null,
        );
    }

    /** Prüft auf ein neues Release und merkt sich das Ergebnis für die Admin-Anzeige. */
    public function check(): array
    {
        $state = [
            'current' => $this->currentVersion(),
            'latest' => null,
            'available' => false,
            'error' => null,
            'checked_at' => now()->toIso8601String(),
        ];

        if (! $this->enabled()) {
            $state['error'] = 'Das Update-System ist abgeschaltet.';
        } else {
            try {
                if ($release = $this->fetchLatest()) {
                    $state['latest'] = $release->toArray();
                    $state['available'] = version_compare($release->version, $state['current'], '>');
                }
            } catch (UpdaterException $e) {
                $state['error'] = $e->getMessage();
            }
        }

        Cache::put(self::CACHE_KEY, $state, now()->addDays(14));

        return $state;
    }

    public function lastCheck(): ?array
    {
        $state = Cache::get(self::CACHE_KEY);
        if (is_array($state) && ($state['current'] ?? null) !== $this->currentVersion()) {
            // Stand ist veraltet (z. B. nach einem Update) – neu bewerten
            if (! empty($state['latest']['version'])) {
                $state['current'] = $this->currentVersion();
                $state['available'] = version_compare($state['latest']['version'], $state['current'], '>');
            } else {
                return null;
            }
        }

        return is_array($state) ? $state : null;
    }

    public function updateAvailable(): bool
    {
        return $this->enabled() && (bool) ($this->lastCheck()['available'] ?? false);
    }

    /** @return array<int, array{label: string, ok: bool, blocking: bool, hint: string}> */
    public function requirements(): array
    {
        $base = $this->basePath();
        $work = $this->workDir();
        @mkdir($work, 0775, true);

        $writable = is_writable($base);
        foreach (['app', 'config', 'routes', 'resources', 'public', 'vendor'] as $d) {
            if (is_dir("$base/$d") && ! is_writable("$base/$d")) {
                $writable = false;
            }
        }
        $free = @disk_free_space(is_dir($work) ? $work : dirname($work));
        $need = 400 * 1024 * 1024;
        $php = (new PhpExecutableFinder)->find(false);

        return [
            ['label' => 'PHP-Erweiterung zip', 'ok' => class_exists(\ZipArchive::class), 'blocking' => true, 'hint' => 'ZipArchive wird zum Entpacken gebraucht.'],
            ['label' => 'Projektordner beschreibbar', 'ok' => $writable, 'blocking' => true, 'hint' => 'Der Webserver-Benutzer muss Dateien ersetzen dürfen.'],
            ['label' => 'Arbeitsordner beschreibbar', 'ok' => is_dir($work) && is_writable($work), 'blocking' => true, 'hint' => $work],
            ['label' => 'Freier Speicherplatz (mind. 400 MB)', 'ok' => $free === false || $free >= $need, 'blocking' => true, 'hint' => $free === false ? 'Konnte nicht geprüft werden.' : round($free / 1048576).' MB frei'],
            ['label' => 'Eigener PHP-Prozess für das Update', 'ok' => $php && function_exists('proc_open') && DIRECTORY_SEPARATOR === '/', 'blocking' => false, 'hint' => 'Ohne diesen läuft das Update direkt im Webrequest (funktioniert, ist aber weniger robust).'],
        ];
    }

    // ---------- Status der laufenden Installation ----------

    public function statusPath(): string
    {
        return $this->workDir('status.json');
    }

    public function readStatus(): array
    {
        $file = $this->statusPath();
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($data)) {
            return ['state' => 'idle', 'step' => '', 'message' => '', 'percent' => 0, 'log' => []];
        }
        // Ein „laufendes“ Update ohne Lebenszeichen seit 30 Minuten gilt als abgebrochen.
        if (($data['state'] ?? '') === 'running' && isset($data['updated_at']) && now()->diffInMinutes($data['updated_at'], true) > 30) {
            $data['state'] = 'failed';
            $data['message'] = 'Das Update wurde nicht abgeschlossen (keine Rückmeldung seit 30 Minuten).';
        }

        return $data;
    }

    public function writeStatus(array $data): void
    {
        @mkdir($this->workDir(), 0775, true);
        $data['updated_at'] = now()->toIso8601String();
        file_put_contents($this->statusPath(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function isRunning(): bool
    {
        return ($this->readStatus()['state'] ?? '') === 'running';
    }

    /** @return array<int, array{name: string, created: string}> */
    public function backups(): array
    {
        $dir = $this->workDir('backups');
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (array_reverse(scandir($dir)) as $n) {
            if ($n[0] !== '.' && is_dir("$dir/$n")) {
                $out[] = ['name' => $n, 'created' => date('d.m.Y H:i', filemtime("$dir/$n"))];
            }
        }

        return $out;
    }
}
