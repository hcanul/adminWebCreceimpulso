<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Local branch timezone; durable claims prevent duplicates across scheduler runs.
\Illuminate\Support\Facades\Schedule::command('clients:payment-reminders')->dailyAt('09:00')->timezone('America/Cancun')->withoutOverlapping(30);
