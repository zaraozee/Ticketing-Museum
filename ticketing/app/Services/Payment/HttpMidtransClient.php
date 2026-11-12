<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implementasi Midtrans lewat HTTP (D-31).
 *
 * Server Key hanya dipakai di sini dan tidak pernah dikirim ke frontend
 * (THIRD_PARTY §1.1 langkah 4).
 */
class HttpMidtransClient implements MidtransClient
{
    public function isLive(): bool
    {
        $key = config('services.midtrans.server_key');

        return is_string($key) && $key !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, redirect_url: string|null}
     */
    public function createSnapTransaction(array $payload): array
    {
        $this->ensureConfigured();

        try {
            $response = Http::withBasicAuth($this->serverKey(), '')
                ->timeout((int) config('services.midtrans.timeout', 15))
                ->acceptJson()
                ->post($this->snapBaseUrl().'/snap/v1/transactions', $payload);
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException('Gagal menghubungi Midtrans: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            Log::warning('midtrans.snap_failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new PaymentGatewayException(
                'Midtrans menolak pembuatan transaksi (HTTP '.$response->status().').',
            );
        }

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new PaymentGatewayException('Respons Midtrans tidak memuat token Snap.');
        }

        return [
            'token' => $token,
            'redirect_url' => $response->json('redirect_url'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchTransactionStatus(string $orderId): array
    {
        $this->ensureConfigured();

        try {
            $response = Http::withBasicAuth($this->serverKey(), '')
                ->timeout((int) config('services.midtrans.timeout', 15))
                ->acceptJson()
                ->get($this->statusBaseUrl().'/v2/'.rawurlencode($orderId).'/status');
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException('Gagal menghubungi Midtrans: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new PaymentGatewayException(
                'Midtrans menolak permintaan status (HTTP '.$response->status().').',
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new PaymentGatewayException('Respons status Midtrans tidak dapat dibaca.');
        }

        return $data;
    }

    /**
     * SHA512(order_id + status_code + gross_amount + ServerKey) (THIRD_PARTY §1.3).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload): bool
    {
        $provided = $payload['signature_key'] ?? null;

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $expected = hash('sha512',
            (string) ($payload['order_id'] ?? '').
            (string) ($payload['status_code'] ?? '').
            (string) ($payload['gross_amount'] ?? '').
            $this->serverKey()
        );

        // Perbandingan constant-time untuk mencegah timing attack.
        return hash_equals($expected, $provided);
    }

    private function ensureConfigured(): void
    {
        if (! $this->isLive()) {
            throw new PaymentGatewayException(
                'MIDTRANS_SERVER_KEY belum diatur; transaksi tidak dapat dibuat.',
            );
        }
    }

    private function serverKey(): string
    {
        return (string) config('services.midtrans.server_key');
    }

    private function snapBaseUrl(): string
    {
        return rtrim((string) config('services.midtrans.api_base_url'), '/');
    }

    private function statusBaseUrl(): string
    {
        return rtrim((string) config('services.midtrans.status_api_base_url'), '/');
    }
}
