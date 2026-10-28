<?php

namespace App\Http\Controllers;

use App\Support\Cron;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * Führt den Scheduler per Aufruf einer Adresse aus (für Hoster ohne Cron oder externe Cron-Dienste).
 * Das Token steckt in der Adresse; ohne gültiges Token antwortet die Route wie eine unbekannte Seite.
 */
class CronController extends Controller
{
    public function run(string $token): JsonResponse
    {
        $expected = Cron::token();
        if ($expected === null || ! hash_equals($expected, $token)) {
            abort(404);
        }

        // Nie zwei Läufe gleichzeitig (Dateisperre, unabhängig von Cache und Datenbank)
        $lock = @fopen(storage_path('framework/cron.lock'), 'c');
        if ($lock && ! flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->reply(['ok' => true, 'skipped' => 'Ein Lauf ist noch aktiv.']);
        }

        @set_time_limit(120);
        try {
            $code = Artisan::call('schedule:run');
            Cron::markRun();
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        return $this->reply(['ok' => $code === 0, 'at' => now()->toIso8601String()], $code === 0 ? 200 : 500);
    }

    private function reply(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'no-store');
    }
}
