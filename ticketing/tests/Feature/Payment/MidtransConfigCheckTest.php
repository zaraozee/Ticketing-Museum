<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
 * Perintah `payments:check-midtrans` adalah jaring pengaman AC-4: ia harus
 * menangkap ketidakcocokan konfigurasi **sebelum** uang sungguhan dipakai.
 * Tes di bawah mengunci tiga jebakan yang gejalanya menyesatkan di runtime.
 */

function midtransConfig(array $overrides = []): void
{
    config()->set('services.midtrans.server_key', 'SB-Mid-server-aaaaaaaaaaaaaaaaaaaa');
    config()->set('services.midtrans.client_key', 'SB-Mid-client-bbbbbbbbbbbbbbbbbbbb');
    config()->set('services.midtrans.signature_key', 'SB-Mid-server-cccccccccccccccccccc');
    config()->set('services.midtrans.is_production', false);
    config()->set('services.midtrans.snap_url', 'https://app.sandbox.midtrans.com/snap/snap.js');
    config()->set('services.midtrans.api_base_url', 'https://app.sandbox.midtrans.com');
    config()->set('services.midtrans.status_api_base_url', 'https://api.sandbox.midtrans.com');
    config()->set('app.url', 'http://localhost:8000');

    foreach ($overrides as $key => $value) {
        config()->set($key, $value);
    }
}

test('the midtrans check reports missing credentials instead of passing silently', function () {
    midtransConfig([
        'services.midtrans.server_key' => '',
        'services.midtrans.client_key' => '',
        'services.midtrans.signature_key' => '',
    ]);

    $this->artisan('payments:check-midtrans')
        ->expectsOutputToContain('MIDTRANS_SERVER_KEY: kosong')
        ->expectsOutputToContain('MIDTRANS_CLIENT_KEY: kosong')
        ->assertFailed();
});

test('the midtrans check passes with a consistent sandbox configuration', function () {
    midtransConfig();

    $this->artisan('payments:check-midtrans')
        ->expectsOutputToContain('Konfigurasi konsisten')
        ->assertSuccessful();
});

test('the midtrans check rejects sandbox keys while production mode is on', function () {
    // Kunci SB-Mid- hanya berlaku di sandbox. Bila lolos, Snap.js produksi
    // menolak token sandbox dan pembayaran gagal tanpa pesan yang jelas.
    midtransConfig([
        'services.midtrans.is_production' => true,
        'services.midtrans.snap_url' => 'https://app.midtrans.com/snap/snap.js',
        'services.midtrans.api_base_url' => 'https://app.midtrans.com',
        'services.midtrans.status_api_base_url' => 'https://api.midtrans.com',
    ]);

    $this->artisan('payments:check-midtrans')
        ->expectsOutputToContain('kunci sandbox pada mode produksi')
        ->assertFailed();
});

test('the midtrans check rejects production keys while sandbox mode is on', function () {
    midtransConfig([
        'services.midtrans.server_key' => 'Mid-server-aaaaaaaaaaaaaaaaaaaa',
        'services.midtrans.client_key' => 'Mid-client-bbbbbbbbbbbbbbbbbbbb',
        'services.midtrans.signature_key' => 'Mid-server-cccccccccccccccccccc',
    ]);

    $this->artisan('payments:check-midtrans')
        ->expectsOutputToContain('kunci produksi pada mode sandbox')
        ->assertFailed();
});

test('the midtrans check catches a snap url left pointing at the sandbox in production', function () {
    // MIDTRANS_SNAP_URL yang diisi manual tidak ikut berubah saat mode
    // produksi dinyalakan — sumber kesalahan yang tidak terlihat dari kode.
    midtransConfig([
        'services.midtrans.is_production' => true,
        'services.midtrans.server_key' => 'Mid-server-aaaaaaaaaaaaaaaaaaaa',
        'services.midtrans.client_key' => 'Mid-client-bbbbbbbbbbbbbbbbbbbb',
        'services.midtrans.signature_key' => 'Mid-server-cccccccccccccccccccc',
        'services.midtrans.api_base_url' => 'https://app.midtrans.com',
        'services.midtrans.status_api_base_url' => 'https://api.midtrans.com',
        'app.url' => 'https://tiket.museum.example',
        'services.midtrans.snap_url' => 'https://app.sandbox.midtrans.com/snap/snap.js',
    ]);

    $this->artisan('payments:check-midtrans')
        ->expectsOutputToContain('Snap.js: https://app.sandbox.midtrans.com/snap/snap.js')
        ->expectsOutputToContain('MIDTRANS_SNAP_URL diisi manual')
        ->assertFailed();
});

test('the midtrans check rejects localhost as the webhook target in production', function () {
    // Pembayaran akan lunas di Midtrans tetapi tiket tidak pernah terbit.
    midtransConfig([
        'services.midtrans.is_production' => true,
        'services.midtrans.server_key' => 'Mid-server-aaaaaaaaaaaaaaaaaaaa',
        'services.midtrans.client_key' => 'Mid-client-bbbbbbbbbbbbbbbbbbbb',
        'services.midtrans.signature_key' => 'Mid-server-cccccccccccccccccccc',
        'services.midtrans.snap_url' => 'https://app.midtrans.com/snap/snap.js',
        'services.midtrans.api_base_url' => 'https://app.midtrans.com',
        'services.midtrans.status_api_base_url' => 'https://api.midtrans.com',
        'app.url' => 'http://localhost:8000',
    ]);

    $this->artisan('payments:check-midtrans')
        ->expectsOutputToContain('localhost pada mode produksi')
        ->assertFailed();
});

test('the midtrans check rejects a non https app url in production', function () {
    midtransConfig([
        'services.midtrans.is_production' => true,
        'services.midtrans.server_key' => 'Mid-server-aaaaaaaaaaaaaaaaaaaa',
        'services.midtrans.client_key' => 'Mid-client-bbbbbbbbbbbbbbbbbbbb',
        'services.midtrans.signature_key' => 'Mid-server-cccccccccccccccccccc',
        'services.midtrans.snap_url' => 'https://app.midtrans.com/snap/snap.js',
        'services.midtrans.api_base_url' => 'https://app.midtrans.com',
        'services.midtrans.status_api_base_url' => 'https://api.midtrans.com',
        'app.url' => 'http://tiket.museum.example',
    ]);

    $this->artisan('payments:check-midtrans')
        ->expectsOutputToContain('APP_URL bukan HTTPS')
        ->assertFailed();
});

test('the probe reports success when midtrans accepts the server key', function () {
    // `404`/`200` dengan "Transaction doesn't exist" berarti kunci diterima.
    midtransConfig();

    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response([
            'status_code' => '404',
            'status_message' => "Transaction doesn't exist.",
        ], 200),
    ]);

    $this->artisan('payments:check-midtrans --probe')
        ->expectsOutputToContain('Kunci diterima Midtrans')
        ->assertSuccessful();
});

test('the probe reports a rejected key when midtrans answers 401', function () {
    // Gejala paling menyesatkan: Server Key diisi Signature Secret Key.
    midtransConfig();

    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response(['status_message' => 'Unauthorized'], 401),
    ]);

    $this->artisan('payments:check-midtrans --probe')
        ->expectsOutputToContain('HTTP 401')
        ->assertFailed();
});

test('the probe reports missing permission when midtrans answers 403', function () {
    midtransConfig();

    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response(['status_message' => 'Forbidden'], 403),
    ]);

    $this->artisan('payments:check-midtrans --probe')
        ->expectsOutputToContain('HTTP 403')
        ->assertFailed();
});

test('the probe reports an unreachable gateway instead of crashing', function () {
    midtransConfig();

    Http::fake([
        'api.sandbox.midtrans.com/*' => fn () => throw new ConnectionException('timeout'),
    ]);

    $this->artisan('payments:check-midtrans --probe')
        ->expectsOutputToContain('Gagal menghubungi Midtrans')
        ->assertFailed();
});

test('the midtrans check never prints a full secret to the terminal', function () {
    // Terminal dan log dapat direkam; kunci penuh tidak boleh ikut tercetak.
    $serverKey = 'SB-Mid-server-SUPERSECRETVALUE123456';
    midtransConfig(['services.midtrans.server_key' => $serverKey]);

    $this->artisan('payments:check-midtrans')
        ->doesntExpectOutputToContain($serverKey)
        ->assertSuccessful();
});
