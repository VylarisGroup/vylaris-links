<?php

namespace App\Support\Install;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Baut die Zwischenspeicher nach einer Änderung der .env neu auf.
 */
class Caches
{
    /** @return array{0: string, 1: string} [ok|warn, Detail] */
    public static function rebuild(): array
    {
        if (config('app.env') !== 'production') {
            Artisan::call('optimize:clear');

            return ['ok', 'Zwischenspeicher geleert (Entwicklungsmodus)'];
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
                return ['ok', 'Konfiguration, Routen und Ansichten zwischengespeichert'];
            }
        } catch (\Throwable) {
        }

        return ['warn', 'Der Cache-Aufbau ist fehlgeschlagen und wird übersprungen. Später: php artisan optimize'];
    }
}
