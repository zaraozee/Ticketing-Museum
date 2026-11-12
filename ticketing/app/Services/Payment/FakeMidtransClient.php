<?php

namespace App\Services\Payment;

use Illuminate\Support\Str;

/**
 * Gateway palsu untuk pengujian (D-31).
 *
 * Meniru bentuk respons Midtrans tanpa menyentuh jaringan, sehingga seluruh
 * alur booking → bayar → tiket dapat diuji deterministik. Implementasi ini
 * **tidak pernah** dipakai bila `MIDTRANS_SERVER_KEY` terisi.
 */
class FakeMidtransClient implements MidtransClient
{
    /** @var array<string, array<string, mixed>> */
    private array $transactions = [];

    /** @var array<int, array<string, mixed>> */
    public array $snapRequests = [];

    /** @var array<int, string> */
    public array $statusRequests = [];

    /**
     * Status yang akan dikembalikan `fetchTransactionStatus`, di-key order_id.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $statusOverrides = [];

    /** @var array<string, bool> */
    private array $signatureResults = [];

    public function isLive(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, redirect_url: string|null}
     */
    public function createSnapTransaction(array $payload): array
    {
        $this->snapRequests[] = $payload;

        $orderId = (string) ($payload['transaction_details']['order_id'] ?? '');
        $token = 'fake-snap-'.Str::random(20);

        $this->transactions[$orderId] = [
            'token' => $token,
            'gross_amount' => (string) ($payload['transaction_details']['gross_amount'] ?? '0'),
        ];

        return [
            'token' => $token,
            'redirect_url' => 'https://sandbox.example.test/snap/'.$token,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchTransactionStatus(string $orderId): array
    {
        $this->statusRequests[] = $orderId;

        if (isset($this->statusOverrides[$orderId])) {
            return $this->statusOverrides[$orderId];
        }

        $transaction = $this->transactions[$orderId] ?? null;

        return [
            'order_id' => $orderId,
            'transaction_id' => 'fake-txn-'.substr(hash('sha256', $orderId), 0, 12),
            'transaction_status' => 'pending',
            'status_code' => '201',
            'gross_amount' => $transaction['gross_amount'] ?? '0.00',
            'payment_type' => 'qris',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload): bool
    {
        $orderId = (string) ($payload['order_id'] ?? '');

        // Hasil yang dipaksakan oleh tes (mis. skenario signature salah).
        if (array_key_exists($orderId, $this->signatureResults)) {
            return $this->signatureResults[$orderId];
        }

        return true;
    }

    /**
     * Paksa status tertentu untuk sebuah order (dipakai tes).
     *
     * @param  array<string, mixed>  $status
     */
    public function forceStatus(string $orderId, array $status): void
    {
        $this->statusOverrides[$orderId] = $status;
    }

    /**
     * Paksa hasil verifikasi signature untuk sebuah order (dipakai tes).
     */
    public function forceSignature(string $orderId, bool $valid): void
    {
        $this->signatureResults[$orderId] = $valid;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function transaction(string $orderId): ?array
    {
        return $this->transactions[$orderId] ?? null;
    }
}
