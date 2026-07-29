<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('erp:backup --scheduled')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/erp-backup-schedule.log'));

Schedule::command('gestor:push-alertas')
    ->dailyAt('08:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/gestor-push-schedule.log'));

Schedule::command('rh:verificar-vencimentos')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/rh-vencimentos-schedule.log'));
