<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('erp:backup --scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/erp-backup-schedule.log'));

Schedule::command('gestor:push-alertas')
    ->dailyAt('08:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/gestor-push-schedule.log'));

Schedule::command('pix:consultar-pendentes')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/pix-consultar-pendentes.log'));

// Exclusão mútua pelo flock do próprio comando (liberado pelo SO se o processo morrer);
// o mutex de cache do withoutOverlapping/runInBackground podia ficar preso por 24h no Windows.
Schedule::command('unitec:hotfix --agendado')
    ->everyFiveMinutes()
    ->appendOutputTo(storage_path('logs/erp-hotfix-run.log'));
