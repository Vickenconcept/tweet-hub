<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the processing of scheduled posts every minute
Schedule::command('posts:process-scheduled')->everyMinute();

// Generate business auto posts daily at 5 AM server time
Schedule::command('business:auto-generate')->dailyAt('05:00');

// Reset Twitter account daily comment counters at midnight
Schedule::command('twitter:reset-daily-counters')->dailyAt('00:00');
