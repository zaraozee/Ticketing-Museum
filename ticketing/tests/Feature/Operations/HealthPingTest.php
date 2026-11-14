<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * `ops:health` sendiri yang memberi tahu Healthchecks.io (AC-5 Fase 9).
 *
 * Memakai endpoint `/fail` saat ada alarm membuat status langsung Down, bukan
 * menunggu grace time habis — perbedaan antara alarm yang berbunyi dalam
 * hitungan detik dan satu yang baru berbunyi beberapa menit kemudian.
 */
beforeEach(function () {
    Http::preventStrayRequests();
    config(['ticketing.monitoring.ping_url' => 'https://hc-ping.com/abc-123']);
});

it('mengirim ping biasa saat semua pemeriksaan sehat', function () {
    Http::fake();

    $this->artisan('ops:health')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hc-ping.com/abc-123'
        && $request->method() === 'POST');
});

it('mengirim ping ke /fail saat ada alarm', function () {
    Http::fake();

    // Pembayaran pending yang melewati ambang adalah alarm nyata.
    Payment::factory()->create([
        'status' => PaymentStatus::PENDING,
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('ops:health')->assertFailed();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hc-ping.com/abc-123/fail');
});

it('menyertakan ringkasan pemeriksaan pada body ping', function () {
    Http::fake();

    Payment::factory()->create([
        'status' => PaymentStatus::PENDING,
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('ops:health')->assertFailed();

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'Pembayaran menggantung'));
});

it('tidak mengirim ping bila URL monitor belum diatur', function () {
    Http::fake();
    config(['ticketing.monitoring.ping_url' => '']);

    $this->artisan('ops:health')->assertSuccessful();

    Http::assertNothingSent();
});

it('tetap menyelesaikan pemeriksaan walau ping gagal terkirim', function () {
    // Monitor yang tidak terjangkau tidak boleh mengubah hasil pemeriksaan:
    // itu masalah terpisah dari kesehatan aplikasi.
    Http::fake(fn () => throw new ConnectionException('tidak terjangkau'));

    $this->artisan('ops:health')->assertSuccessful();
});

it('tidak menghitung pembayaran milik booking yang sudah berakhir', function () {
    Http::fake();

    // Payment `pending` pada booking `expired`/`cancelled` tidak akan pernah
    // dilunasi lewat jalur normal. Menghitungnya membuat alarm ini berbunyi
    // terus-menerus sehingga kehilangan makna sebagai alarm.
    $booking = Booking::factory()->create([
        'status' => BookingStatus::EXPIRED,
        'expired_at' => now(),
    ]);

    Payment::factory()->for($booking)->create([
        'status' => PaymentStatus::PENDING,
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('ops:health')->assertSuccessful();
});

it('tetap menghitung pembayaran menggantung pada booking yang masih berjalan', function () {
    Http::fake();

    // Kebalikan dari tes di atas: alarm harus tetap berbunyi untuk kasus yang
    // memang menjadi sasarannya.
    $booking = Booking::factory()->create(['status' => BookingStatus::PENDING_PAYMENT]);

    Payment::factory()->for($booking)->create([
        'status' => PaymentStatus::PENDING,
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('ops:health')->assertFailed();
});
