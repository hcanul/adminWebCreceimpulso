<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cancun time; planner is idempotent and dispatch runs only after committed messages exist.
Schedule::command('messages:plan-payments')->hourlyAt(5)->between('09:00', '20:30')->timezone('America/Cancun')->withoutOverlapping(30);
Schedule::command('messages:dispatch')->everyMinute()->withoutOverlapping(30);
