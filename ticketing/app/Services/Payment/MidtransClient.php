<?php

namespace App\Services\Payment;

/**
 * Kontrak gateway pembayaran (D-31).
 *
 * Seluruh logika vendor dikurung di balik antarmuka ini agar penggantian
 * gateway hanya mengubah implementasi, bukan model booking/tiket
 * (THIRD_PARTY_INTEGRATIONS §1.6).
 */
interface MidtransClient
{
    /**
     * Buat transaksi Snap dan kembalikan token + URL redirect.
     *
     * Dipanggil dari backend setelah baris `payments` di-commit. Bila gagal,
     * booking tetap `pending_payment` dan pengguna boleh mencoba lagi
     * (THIRD_PARTY §1.1 langkah 5).
     *
     * @param  array<string, mixed>  $payload
     * @return array{token: string, redirect_url: string|null}
     *
     * @throws PaymentGatewayException
     */
    public function createSnapTransaction(array $payload): array;

    /**
     * Ambil status transaksi langsung dari API gateway (BR-10).
     *
     * Webhook **tidak** dipercaya sebagai sumber kebenaran status; nilainya
     * hanya pemicu untuk memanggil endpoint ini.
     *
     * @return array<string, mixed>
     *
     * @throws PaymentGatewayException
     */
    public function fetchTransactionStatus(string $orderId): array;

    /**
     * Verifikasi signature notifikasi (THIRD_PARTY §1.3 langkah 2).
     *
     * Wajib memakai perbandingan constant-time.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload): bool;

    /**
     * Apakah klien ini benar-benar terhubung ke gateway.
     *
     * Dipakai untuk menolak membuat pembayaran palsu di lingkungan produksi.
     */
    public function isLive(): bool;
}
