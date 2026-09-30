<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-expiration-notifications')->daily();

// Every Monday morning, so supervisors start the week with a summary of
// their team's certificate/training/skill-gap status instead of only
// finding out via the daily per-item in-app notifications above.
Schedule::command('app:send-weekly-supervisor-digest')->weeklyOn(1, '07:00');
