<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs `php artisan schedule:work` running (or a cron / Task Scheduler entry for `schedule:run`).
Schedule::command('meta:sync')->everyFifteenMinutes()->withoutOverlapping();
