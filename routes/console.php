<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:send-reminders')->hourly();

Schedule::command('bookings:check-status')->dailyAt('23:00');

Schedule::command('providers:update-ratings')->hourly();

Schedule::command('clean:old-notifications')->daily();

Schedule::command('reports:generate-daily')->dailyAt('00:30');

Schedule::command('backup:run')->dailyAt('02:00');

Schedule::command('backup:clean')->dailyAt('03:00');
