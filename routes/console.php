<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('business:backup')->dailyAt('02:00')->timezone('Africa/Johannesburg')->withoutOverlapping();
Schedule::command('invoices:remind')->dailyAt('08:00')->timezone('Africa/Johannesburg')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
