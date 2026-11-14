<?php

use App\Services\Payment\HttpMidtransClient;
use App\Services\Payment\PaymentGatewayException;
use Illuminate\Support\Facades\Http;

/**
 * Midtrans membalas **HTTP 200** untuk kesalahan tingkat API, dengan kode
 * sebenarnya di dalam body:
 *
 *     {"status_code":"404","status_message":"Transaction doesn't exist."}
 *
 * Kode yang hanya memeriksa lapisan HTTP akan menerima body itu sebagai status
 * transaksi yang sah. Akibatnya kesalahan gateway berubah menjadi anomali palsu
 * di antrean admin, dan penyebab sebenarnya tidak pernah tercatat di log.
 */
beforeEach(function () {
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-uji',
        'services.midtrans.status_api_base_url' => 'https://api.sandbox.midtrans.com',
    ]);
});

it('menolak body kesalahan yang dibungkus HTTP 200', function () {
    Http::fake([
        '*/v2/*/status' => Http::response([
            'status_code' => '404',
            'status_message' => "Transaction doesn't exist.",
            'id' => 'b8c7c4c9',
        ], 200),
    ]);

    expect(fn () => (new HttpMidtransClient)->fetchTransactionStatus('MUS-TIDAK-ADA-1'))
        ->toThrow(PaymentGatewayException::class, "Transaction doesn't exist.");
});

it('menerima status transaksi yang sah', function () {
    Http::fake([
        '*/v2/*/status' => Http::response([
            'status_code' => '200',
            'transaction_status' => 'settlement',
            'order_id' => 'MUS-ADA-1',
            'gross_amount' => '50000.00',
        ], 200),
    ]);

    $status = (new HttpMidtransClient)->fetchTransactionStatus('MUS-ADA-1');

    expect($status['transaction_status'])->toBe('settlement');
});

it('menolak respons tanpa transaction_status', function () {
    // Penjaga terhadap bentuk kesalahan lain yang mungkin belum diketahui.
    Http::fake([
        '*/v2/*/status' => Http::response(['status_code' => '412', 'status_message' => 'Terjadi kesalahan'], 200),
    ]);

    expect(fn () => (new HttpMidtransClient)->fetchTransactionStatus('MUS-X-1'))
        ->toThrow(PaymentGatewayException::class);
});
