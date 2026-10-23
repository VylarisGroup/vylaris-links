<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Updater\UpdateRunner;
use App\Support\Updater\Updater;
use App\Support\Updater\UpdaterException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class UpdateController extends Controller
{
    public function index(Updater $updater)
    {
        return view('admin.update', [
            'enabled' => $updater->enabled(),
            'repo' => $updater->config('repo'),
            'asset' => $updater->config('asset'),
            'current' => $updater->currentVersion(),
            'state' => $updater->lastCheck(),
            'requirements' => $updater->requirements(),
            'status' => $updater->readStatus(),
            'backups' => $updater->backups(),
        ]);
    }

    public function check(Updater $updater)
    {
        $s = $updater->check();

        if ($s['error']) {
            return back()->withErrors(['update' => $s['error']]);
        }

        return back()->with('status', $s['available']
            ? "Neue Version {$s['latest']['version']} verfügbar."
            : 'Du bist auf dem neuesten Stand (Version '.$s['current'].').');
    }

    public function status(Updater $updater)
    {
        return response()->json($updater->readStatus())->header('Cache-Control', 'no-store');
    }

    public function install(Updater $updater, UpdateRunner $runner)
    {
        abort_unless($updater->enabled(), 404);

        if ($updater->isRunning()) {
            return response()->json(['error' => 'Es läuft bereits ein Update.'], 409);
        }

        $state = $updater->check();
        if ($state['error']) {
            return response()->json(['error' => $state['error']], 422);
        }
        if (! $state['available']) {
            return response()->json(['error' => 'Du bist bereits auf dem neuesten Stand.'], 422);
        }

        $updater->writeStatus([
            'state' => 'running', 'step' => 'queued', 'message' => 'Update wird gestartet …', 'percent' => 1,
            'from' => $state['current'], 'to' => $state['latest']['version'], 'log' => [], 'started_at' => now()->toIso8601String(),
        ]);

        // Bevorzugt in einem eigenen PHP-Prozess, damit das Update den laufenden Webrequest überlebt.
        $php = (new PhpExecutableFinder)->find(false);
        if ($php && function_exists('proc_open') && DIRECTORY_SEPARATOR === '/') {
            $cmd = sprintf(
                'nohup %s %s links:update --yes --no-interaction > %s 2>&1 &',
                escapeshellarg($php), escapeshellarg(base_path('artisan')), escapeshellarg($updater->workDir('last-run.log'))
            );
            $p = Process::fromShellCommandline($cmd, base_path(), null, null, 15);
            $p->run();

            return response()->json(['started' => true, 'mode' => 'process'], 202);
        }

        // Ausweichlösung: direkt in diesem Request ausführen
        ignore_user_abort(true);
        @set_time_limit(0);
        try {
            $runner->run();
        } catch (UpdaterException $e) {
            return response()->json(['started' => true, 'mode' => 'inline', 'error' => $e->getMessage()]);
        }

        return response()->json(['started' => true, 'mode' => 'inline']);
    }
}
