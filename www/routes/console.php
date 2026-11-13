<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('domains:run-daily-checks')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('reports:send-weekly')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping();

Schedule::command('keywords:run-weekly-checks')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping();

Schedule::command('backlinks:run-monthly-fetch')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping();

Schedule::command('backlinks:sync-crawlgraph')
    ->dailyAt('05:00')
    ->withoutOverlapping();

Schedule::command('keywords:collect-queued-serp')
    ->everyFiveMinutes()
    ->withoutOverlapping();
