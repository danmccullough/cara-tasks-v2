<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('chores:mark-missed')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('occurrences:generate')->dailyAt('00:05')->timezone(config('chores.timezone'));
