<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fase 4: buat slot kunjungan dari template setiap dini hari. Idempoten,
// sehingga aman bila penjadwalan terlewat lalu dijalankan ulang.
Schedule::command('slots:generate')->dailyAt('01:00');
