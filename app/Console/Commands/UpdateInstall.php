<?php

namespace App\Console\Commands;

use App\Support\Updater\UpdateRunner;
use App\Support\Updater\Updater;
use App\Support\Updater\UpdaterException;
use Illuminate\Console\Command;

class UpdateInstall extends Command
{
    protected $signature = 'links:update {--yes : Ohne Rückfrage installieren} {--force : Auch gleiche oder ältere Version installieren}';

    protected $description = 'Installiert das neueste Release aus der source-code.zip (mit Backup, Wartungsmodus und Rollback).';

    public function handle(Updater $updater, UpdateRunner $runner): int
    {
        $state = $updater->check();
        if ($state['error']) {
            $this->error($state['error']);

            return self::FAILURE;
        }
        if (! $state['available'] && ! $this->option('force')) {
            $this->info("Du bist bereits auf dem neuesten Stand (Version {$state['current']}).");

            return self::SUCCESS;
        }

        $to = $state['latest']['version'] ?? '?';
        if (! $this->option('yes') && ! $this->confirm("Von {$state['current']} auf {$to} aktualisieren? Die Seite geht kurz in den Wartungsmodus.")) {
            return self::SUCCESS;
        }

        try {
            $result = $runner->onStep(fn (string $line) => $this->line($line))->run((bool) $this->option('force'));
        } catch (UpdaterException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Fertig: {$result['from']} → {$result['to']}.");

        return self::SUCCESS;
    }
}
