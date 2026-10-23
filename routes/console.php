<?php

use Illuminate\Support\Facades\Schedule;

// Jeden Montag um 07:30 (Europe/Zurich) den Wochenbericht verschicken.
Schedule::command('links:weekly-report')->weeklyOn(1, '07:30');
