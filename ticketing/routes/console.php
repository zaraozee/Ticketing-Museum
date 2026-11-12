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

// Fase 5: lepas kuota booking yang melewati batas bayar (FR-PAY-06, BR-06).
// Dijalankan tiap menit karena batas bayar hanya 15 menit.
Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping();

// Fase 5: cadangan bila webhook hilang (THIRD_PARTY §1.3 langkah 8).
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();

// Fase 6: tandai tiket expired setelah tanggal kunjungan lewat (FR-TIX-01).
Schedule::command('tickets:expire')->dailyAt('02:00');
