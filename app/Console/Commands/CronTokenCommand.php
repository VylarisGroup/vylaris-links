<?php

namespace App\Console\Commands;

use App\Support\Cron;
use App\Support\EnvFile;
use App\Support\Install\Caches;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CronTokenCommand extends Command
{
    protected $signature = 'links:cron-token {--regenerate : Ein neues Token erzeugen (die alte Adresse funktioniert dann nicht mehr)}';

    protected $description = 'Zeigt die Cron-Adresse (/cron/<Token>) und legt bei Bedarf ein Token an.';

    public function handle(): int
    {
        $env = new EnvFile(app()->environmentFilePath());
        $token = $env->get('LINKS_CRON_TOKEN');

        if (! $token || strlen($token) < 32 || $this->option('regenerate')) {
            $token = Str::random(40);
            $env->set('LINKS_CRON_TOKEN', $token);
            config(['links.cron.token' => $token]);
            $this->components->info('Neues Token gespeichert.');
            [$state, $detail] = Caches::rebuild();
            $state === 'ok' ? $this->components->twoColumnDetail('Zwischenspeicher', $detail) : $this->components->warn($detail);
        }

        $this->newLine();
        $this->line('Rufe diese Adresse jede Minute auf (z. B. mit cron-job.org):');
        $this->line('  <info>'.Cron::url($env->get('APP_URL') ?: config('app.url'), $token).'</info>');

        return self::SUCCESS;
    }
}
