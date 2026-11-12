<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Museum;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Alarm operasional (NFR-13, AC-5 Fase 9)
|--------------------------------------------------------------------------
|
| Alarm hanya berguna bila benar-benar berbunyi. Setiap tes di bawah memicu
| satu kondisi gagal dan memastikan `ops:health` keluar dengan kode ≠ 0,
| sehingga monitor eksternal dapat memasang alarm pada exit code.
|
*/

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->museum = Museum::factory()->published()->create();
});

test('a healthy system exits with success', function (): void {
    $this->artisan('ops:health')->assertSuccessful();
});

test('failed jobs above the threshold raise the alarm', function (): void {
    $threshold = (int) config('ticketing.monitoring.failed_jobs_threshold');

    for ($i = 0; $i <= $threshold; $i++) {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException',
            'failed_at' => now(),
        ]);
    }

    $this->artisan('ops:health')
        ->expectsOutputToContain('FAIL')
        ->assertFailed();
});

test('a job waiting longer than the threshold raises the alarm', function (): void {
    $minutes = (int) config('ticketing.monitoring.queue_age_minutes');

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes($minutes + 5)->timestamp,
        'created_at' => now()->subMinutes($minutes + 5)->timestamp,
    ]);

    $this->artisan('ops:health')
        ->expectsOutputToContain('Antrean menunggu')
        ->assertFailed();
});

test('a stale pending payment raises the alarm', function (): void {
    $minutes = (int) config('ticketing.monitoring.stuck_payment_minutes');

    $booking = Booking::factory()
        ->forUser($this->user)
        ->for($this->museum)
        ->create();

    Payment::create([
        'booking_id' => $booking->id,
        'provider' => 'midtrans',
        'provider_order_id' => $booking->booking_code.'-1',
        'gross_amount' => $booking->total_amount,
        'status' => PaymentStatus::PENDING,
        'expires_at' => now()->addMinutes(10),
    ])->forceFill(['created_at' => now()->subMinutes($minutes + 5)])->save();

    $this->artisan('ops:health')
        ->expectsOutputToContain('Pembayaran menggantung')
        ->assertFailed();
});

test('a valid webhook left unprocessed raises the alarm', function (): void {
    $minutes = (int) config('ticketing.monitoring.webhook_lag_minutes');

    $booking = Booking::factory()
        ->forUser($this->user)
        ->for($this->museum)
        ->create();

    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => 'midtrans',
        'provider_order_id' => $booking->booking_code.'-1',
        'gross_amount' => $booking->total_amount,
        'status' => PaymentStatus::PENDING,
        'expires_at' => now()->addMinutes(10),
    ]);

    DB::table('payment_events')->insert([
        'payment_id' => $payment->id,
        'provider' => 'midtrans',
        'event_key' => hash('sha256', 'uji-alarm'),
        'signature_valid' => true,
        'payload' => '{}',
        'process_result' => 'applied',
        'processed_at' => null,
        'created_at' => now()->subMinutes($minutes + 5),
        'updated_at' => now()->subMinutes($minutes + 5),
    ]);

    $this->artisan('ops:health')
        ->expectsOutputToContain('Webhook tertunda')
        ->assertFailed();
});

test('the json output can be consumed by an external monitor', function (): void {
    $this->artisan('ops:health', ['--json' => true])->assertSuccessful();
});
