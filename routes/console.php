<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('qammaris-app:sync')->everyThirtyMinutes()->withoutOverlapping();
// Order API v1 (ORD-02e): webhook retries every minute; idempotency/outbox housekeeping daily.
Schedule::command('qammaris:orders:deliver-webhooks')->everyMinute()->withoutOverlapping();
Schedule::command('qammaris:orders:prune-api')->dailyAt('03:30');
// WAIT-01: "Kabari saya saat ready" requests to the App Daftar Tunggu.
Schedule::command('qammaris:restock-requests:deliver')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
