<?php

namespace App\Console\Commands;

use App\Support\Updater\Updater;
use Illuminate\Console\Command;

class UpdateCheck extends Command
{
    protected $signature = 'links:update-check';

    protected $description = 'Prüft, ob im GitHub-Repo ein neueres Release mit source-code.zip vorliegt.';

    public function handle(Updater $updater): int
    {
        $s = $updater->check();

        if ($s['error']) {
            $this->warn('Prüfung nicht möglich: '.$s['error']);

            return self::FAILURE;
        }

        if ($s['available']) {
            $this->info("Neue Version verfügbar: {$s['latest']['version']} (installiert: {$s['current']}). Installieren mit: php artisan links:update");
        } else {
            $this->info("Aktuell (Version {$s['current']}).");
        }

        return self::SUCCESS;
    }
}
