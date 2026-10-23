<?php

namespace App\Console\Commands;

use App\Mail\WeeklyReport;
use App\Models\Item;
use App\Models\User;
use App\Support\Stats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyReports extends Command
{
    protected $signature = 'links:weekly-report';

    protected $description = 'Sendet allen Nutzern mit aktiviertem Wochenbericht die Zahlen der letzten 7 Tage.';

    public function handle(): int
    {
        $sent = 0;
        User::where('weekly_report', true)->whereNull('blocked_at')->with('pages')->chunkById(100, function ($users) use (&$sent) {
            foreach ($users as $user) {
                $rows = [];
                foreach ($user->pages as $page) {
                    $stats = Stats::summary($page, 7);
                    $best = $stats['items']->isNotEmpty() ? Item::find($stats['items']->keys()->first()) : null;
                    $rows[] = ['page' => $page, 'stats' => $stats, 'best' => $best];
                }
                if ($rows) {
                    Mail::to($user->email)->send(new WeeklyReport($user, $rows));
                    $sent++;
                }
            }
        });

        $this->info("{$sent} Wochenberichte versendet.");

        return self::SUCCESS;
    }
}
