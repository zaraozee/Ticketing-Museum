<?php

namespace App\Services\Payment;

use App\Enums\PaymentEventResult;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pemrosesan notifikasi pembayaran (FR-PAY-02…04, BR-10…BR-12).
 *
 * Urutan yang mengikat:
 * 1. verifikasi signature (constant-time) → gagal: catat, jangan sentuh status;
 * 2. ambil status dari **API gateway**, bukan dari payload mentah (BR-10);
 * 3. klaim `payment_events.event_key` **dan** terapkan status dalam **satu
 *    transaksi**. Bila penerapan gagal, klaim ikut ter-rollback sehingga
 *    notifikasi ulang dari gateway masih dapat diproses.
 *
 * Langkah 3 sengaja tidak memakai pola "klaim dulu, proses kemudian": klaim
 * yang tidak ikut ter-rollback akan membuat retry dianggap duplikat, dan
 * pembayaran yang sah tidak pernah dikonfirmasi.
 */
class WebhookProcessor
{
    public function __construct(
        private readonly MidtransClient $client,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Proses satu payload notifikasi.
     *
     * Tidak pernah melempar exception untuk payload yang tidak sah: webhook
     * harus tetap dijawab agar gateway tidak mengirim ulang tanpa henti.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): PaymentEventResult
    {
        $orderId = $this->stringOrNull($payload['order_id'] ?? null);
        $signatureValid = $this->client->verifySignature($payload);

        $payment = $orderId === null
            ? null
            : Payment::query()->where('provider_order_id', $orderId)->first();

        if (! $signatureValid) {
            $this->recordRejected($payment, $payload);

            Log::warning('payment.webhook_rejected_signature', [
                'order_id' => $orderId,
                'ip' => request()->ip(),
            ]);

            return PaymentEventResult::REJECTED_SIGNATURE;
        }

        if ($payment === null) {
            $this->recordEvent(null, $payload, true, PaymentEventResult::UNKNOWN_ORDER, now());

            return PaymentEventResult::UNKNOWN_ORDER;
        }

        // BR-10: status diambil dari API, bukan dari payload. Dilakukan di luar
        // transaksi database agar lock tidak ditahan selama menunggu jaringan.
        try {
            $status = $this->client->fetchTransactionStatus($orderId);
        } catch (PaymentGatewayException $e) {
            Log::error('payment.status_verification_failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            // Tidak ada yang dicatat: notifikasi ulang harus dapat diproses.
            return PaymentEventResult::NEEDS_REVIEW;
        }

        try {
            return DB::transaction(function () use ($payment, $payload, $status): PaymentEventResult {
                // Klaim idempotensi + perubahan status commit bersama.
                $event = $this->recordEvent(
                    $payment,
                    $payload,
                    true,
                    PaymentEventResult::APPLIED,
                    null,
                );

                if ($event === null) {
                    return PaymentEventResult::IGNORED_DUPLICATE;
                }

                $result = $this->payments->applyGatewayStatus($payment, $status);

                $event->forceFill([
                    'process_result' => $result,
                    'processed_at' => now(),
                ])->save();

                return $result;
            });
        } catch (UniqueConstraintViolationException) {
            return PaymentEventResult::IGNORED_DUPLICATE;
        }
    }

    /**
     * Catat notifikasi dengan signature tidak sah.
     *
     * Sengaja **tidak** memakai kunci idempoten yang sama dengan notifikasi
     * sah: bila dipakai bersama, penyerang dapat mengirim payload palsu lebih
     * dulu untuk "meracuni" kunci sehingga notifikasi asli dianggap duplikat.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordRejected(?Payment $payment, array $payload): void
    {
        $this->recordEvent($payment, $payload, false, PaymentEventResult::REJECTED_SIGNATURE, now());
    }

    /**
     * Simpan jejak notifikasi. Mengembalikan null bila kunci sudah ada.
     *
     * `INSERT` dibungkus transaksi bersarang (savepoint) dengan sengaja: pada
     * PostgreSQL, satu pelanggaran unique membatalkan **seluruh** transaksi
     * sampai di-rollback ke savepoint. Tanpa savepoint, menangkap
     * `UniqueConstraintViolationException` di dalam transaksi induk akan
     * meninggalkan transaksi dalam keadaan rusak dan semua perintah
     * berikutnya gagal.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        ?Payment $payment,
        array $payload,
        bool $signatureValid,
        PaymentEventResult $result,
        ?\DateTimeInterface $processedAt,
    ): ?PaymentEvent {
        try {
            return DB::transaction(fn (): PaymentEvent => PaymentEvent::create([
                'payment_id' => $payment?->id,
                'provider' => 'midtrans',
                'event_key' => $this->eventKey($payload, $signatureValid),
                'signature_valid' => $signatureValid,
                'payload' => $payload,
                'process_result' => $result,
                'processed_at' => $processedAt,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Kunci idempoten sudah ada: peristiwa ini pernah diproses.
            return null;
        }
    }

    /**
     * Kunci idempotensi (ERD §3.4):
     * hash(`order_id|transaction_status|status_code|gross_amount|transaction_id`).
     *
     * `signature_valid` ikut masuk kunci agar notifikasi sah dan palsu tidak
     * saling menutupi.
     *
     * @param  array<string, mixed>  $payload
     */
    private function eventKey(array $payload, bool $signatureValid): string
    {
        $parts = [
            (string) ($payload['order_id'] ?? ''),
            (string) ($payload['transaction_status'] ?? ''),
            (string) ($payload['status_code'] ?? ''),
            (string) ($payload['gross_amount'] ?? ''),
            (string) ($payload['transaction_id'] ?? ''),
            $signatureValid ? 'sig-ok' : 'sig-bad',
        ];

        return hash('sha256', implode('|', $parts));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
