<?php

use App\Services\Payment\HttpMidtransClient;

/**
 * Notifikasi Midtrans ditandatangani dengan **Signature Secret Key**, bukan
 * Server Key (perubahan Midtrans 28 Oktober 2026).
 *
 * Bila kode kembali memakai Server Key untuk memverifikasi signature, setiap
 * notifikasi akan ditolak begitu merchant meregenerasi Signature Secret Key —
 * pembayaran lunas tidak pernah menjadi tiket. Tes ini mengunci perilakunya.
 */
it('memverifikasi signature memakai Signature Secret Key, bukan Server Key', function () {
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-otorisasi-api',
        'services.midtrans.signature_key' => 'SB-Mid-signature-penanda-tangan',
    ]);

    $payload = [
        'order_id' => 'MUS-ABC123-1',
        'status_code' => '200',
        'gross_amount' => '150000.00',
    ];

    // Midtrans menandatangani dengan Signature Secret Key.
    $payload['signature_key'] = hash('sha512',
        $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-signature-penanda-tangan'
    );

    expect((new HttpMidtransClient)->verifySignature($payload))->toBeTrue();
});

it('menolak signature yang dihitung dengan Server Key', function () {
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-otorisasi-api',
        'services.midtrans.signature_key' => 'SB-Mid-signature-penanda-tangan',
    ]);

    $payload = [
        'order_id' => 'MUS-ABC123-1',
        'status_code' => '200',
        'gross_amount' => '150000.00',
    ];

    $payload['signature_key'] = hash('sha512',
        $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-server-otorisasi-api'
    );

    expect((new HttpMidtransClient)->verifySignature($payload))->toBeFalse();
});

it('jatuh kembali ke Server Key bila Signature Secret Key belum dipisahkan', function () {
    // Akun yang belum meregenerasi Signature Secret Key memakai nilai yang
    // sama dengan Server Key; integrasi lama harus tetap berjalan.
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-satu-satunya-kunci',
        'services.midtrans.signature_key' => null,
    ]);

    $payload = [
        'order_id' => 'MUS-ABC123-1',
        'status_code' => '200',
        'gross_amount' => '150000.00',
    ];

    $payload['signature_key'] = hash('sha512',
        $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-server-satu-satunya-kunci'
    );

    expect((new HttpMidtransClient)->verifySignature($payload))->toBeTrue();
});
