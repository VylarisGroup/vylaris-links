<?php

use Illuminate\Support\Facades\Schedule;

// Jeden Montag um 07:30 (Europe/Zurich) den Wochenbericht verschicken.
Schedule::command('links:weekly-report')->weeklyOn(1, '07:30');

// Jede Nacht prüfen, ob ein neues Release vorliegt (installiert wird nur per Klick im Admin-Bereich).
Schedule::command('links:update-check')->dailyAt('03:15')->when(fn () => config('updater.enabled'));
