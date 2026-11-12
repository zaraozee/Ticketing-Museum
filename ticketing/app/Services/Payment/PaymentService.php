<?php

namespace App\Services\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentEventResult;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\VisitSlot;
use App\Services\Audit\ActivityLogger;
use App\Services\Quota\QuotaService;
use App\Services\Ticket\TicketIssuer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Pembuatan transaksi pembayaran dan penerapan status dari gateway.
 *
 * **Satu-satunya** tempat yang boleh mengubah `payments.status` dan
 * `bookings.status` (BR-10, §11.2 "kondisi terlarang"). Controller hanya
 * membaca.
 */
class PaymentService
{
    /**
     * BR-09: percobaan bayar baru hanya bila sisa waktu booking ≥ 3 menit.
     */
    private const MIN_REMAINING_SECONDS = 180;

    /**
     * BR-09: transaksi gateway kedaluwarsa ±2 menit sebelum booking.
     */
    private const GATEWAY_EXPIRY_BUFFER_MINUTES = 2;

    public function __construct(
        private readonly MidtransClient $client,
        private readonly QuotaService $quota,
        private readonly TicketIssuer $issuer,
    ) {}

    /**
     * Mulai (atau lanjutkan) pembayaran sebuah booking (FR-PAY-01, FR-BKG-09).
     *
     * Bila sudah ada percobaan aktif yang masih berlaku, percobaan itu
     * dikembalikan tanpa membuat transaksi baru — mencegah dua tagihan untuk
     * satu booking.
     *
     * @throws ValidationException
     */
    public function start(Booking $booking): Payment
    {
        if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
            throw ValidationException::withMessages([
                'booking' => 'Booking ini tidak dapat dibayar lagi.',
            ]);
        }

        if ($booking->expires_at !== null && $booking->secondsUntilExpiry() < self::MIN_REMAINING_SECONDS) {
            throw ValidationException::withMessages([
                'booking' => 'Sisa waktu pembayaran terlalu singkat. Silakan buat pemesanan baru.',
            ]);
        }

        // Lanjutkan percobaan yang masih aktif: jangan buat tagihan kedua.
        $existing = Payment::query()
            ->where('booking_id', $booking->id)
            ->stillValid()
            ->orderByDesc('id')
            ->first();

        if ($existing !== null && $existing->snap_token !== null) {
            return $existing;
        }

        $payment = $this->createAttempt($booking);

        // Panggilan jaringan dilakukan DI LUAR transaksi database: jangan
        // menahan lock selama menunggu gateway.
        try {
            $snap = $this->client->createSnapTransaction($this->snapPayload($booking, $payment));
        } catch (PaymentGatewayException $e) {
            // Booking tetap `pending_payment`; pengguna boleh mencoba lagi
            // (§12.1 langkah 11). Percobaan yang gagal tetap tercatat.
            $payment->forceFill([
                'status' => PaymentStatus::FAILED,
                'review_note' => mb_substr($e->getMessage(), 0, 190),
            ])->save();

            Log::warning('payment.snap_failed', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment' => 'Gagal membuat transaksi pembayaran. Silakan coba lagi.',
            ]);
        }

        $payment->forceFill([
            'snap_token' => $snap['token'],
            'snap_redirect_url' => $snap['redirect_url'],
            'status' => PaymentStatus::PENDING,
        ])->save();

        ActivityLogger::log('payment.started', $payment, [
            'booking_id' => $booking->id,
            'provider_order_id' => $payment->provider_order_id,
            'gross_amount' => $payment->gross_amount,
        ], $booking->user_id);

        return $payment;
    }

    /**
     * Terapkan status dari gateway ke payment dan booking (BR-07, BR-10, BR-23).
     *
     * Seluruh perubahan berada dalam satu transaksi dan memakai lock baris,
     * sehingga notifikasi yang datang bersamaan tidak dapat sama-sama menang.
     *
     * @param  array<string, mixed>  $status  respons API status gateway
     */
    public function applyGatewayStatus(Payment $payment, array $status): PaymentEventResult
    {
        $transactionStatus = (string) ($status['transaction_status'] ?? '');

        if ($transactionStatus === '') {
            return PaymentEventResult::NEEDS_REVIEW;
        }

        return DB::transaction(function () use ($payment, $status, $transactionStatus): PaymentEventResult {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // BR-11: nominal wajib sama. Dibandingkan sebagai rupiah bulat.
            if (! $this->amountsMatch($status, $locked)) {
                $locked->forceFill([
                    'needs_review' => true,
                    'review_note' => 'Nominal gateway tidak cocok dengan tagihan.',
                    'last_response' => $status,
                ])->save();

                Log::warning('payment.amount_mismatch', [
                    'payment_id' => $locked->id,
                    'expected' => $locked->gross_amount,
                    'received' => $status['gross_amount'] ?? null,
                ]);

                return PaymentEventResult::AMOUNT_MISMATCH;
            }

            $target = $this->mapTransactionStatus($transactionStatus, $status);

            if ($target === null) {
                // Status yang tidak dikenal: catat, jangan tebak.
                $locked->forceFill([
                    'needs_review' => true,
                    'review_note' => 'Status gateway tidak dikenal: '.mb_substr($transactionStatus, 0, 60),
                    'last_response' => $status,
                ])->save();

                return PaymentEventResult::NEEDS_REVIEW;
            }

            // Transisi monoton: webhook terlambat dengan status lebih rendah
            // diabaikan, bukan menimpa status yang sudah maju (§11.2).
            if (! $locked->status->canTransitionTo($target)) {
                $locked->forceFill(['last_response' => $status])->save();

                return PaymentEventResult::IGNORED_DUPLICATE;
            }

            $this->applyTarget($locked, $target, $status);

            return PaymentEventResult::APPLIED;
        });
    }

    /**
     * Buat baris `payments` untuk satu percobaan (nomor urut per booking).
     */
    private function createAttempt(Booking $booking): Payment
    {
        $attempt = Payment::query()->where('booking_id', $booking->id)->count() + 1;

        // BR-09: gateway kedaluwarsa lebih awal agar tidak ada pembayaran
        // yang masuk setelah booking sudah dilepas kuotanya.
        $expiresAt = $booking->expires_at !== null
            ? $booking->expires_at->copy()->subMinutes(self::GATEWAY_EXPIRY_BUFFER_MINUTES)
            : now()->addMinutes(self::GATEWAY_EXPIRY_BUFFER_MINUTES);

        if ($expiresAt->isPast()) {
            $expiresAt = now()->addMinute();
        }

        return Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'midtrans',
            'provider_order_id' => $booking->booking_code.'-'.$attempt,
            'gross_amount' => $booking->total_amount,
            'status' => PaymentStatus::CREATED,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Payload Snap (THIRD_PARTY §1.1 tabel "Data dikirim").
     *
     * @return array<string, mixed>
     */
    private function snapPayload(Booking $booking, Payment $payment): array
    {
        $items = $booking->items()->get()->map(fn ($item): array => [
            'id' => (string) $item->ticket_type_id,
            'price' => $item->unit_price,
            'quantity' => $item->quantity,
            'name' => mb_substr($item->ticket_name_snapshot, 0, 50),
        ])->all();

        return [
            'transaction_details' => [
                'order_id' => $payment->provider_order_id,
                'gross_amount' => $booking->total_amount,
            ],
            'item_details' => $items,
            'customer_details' => [
                'first_name' => mb_substr($booking->contact_name, 0, 50),
                'email' => $booking->contact_email,
                'phone' => $booking->contact_phone,
            ],
            // BR-09: batas waktu transaksi gateway.
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => max(1, (int) now()->diffInMinutes($payment->expires_at, false)),
            ],
            'callbacks' => [
                'finish' => route('bookings.show', $booking->uuid),
            ],
        ];
    }

    /**
     * Petakan `transaction_status` Midtrans ke status internal.
     */
    private function mapTransactionStatus(string $transactionStatus, array $status): ?PaymentStatus
    {
        return match ($transactionStatus) {
            'capture' => $this->captureStatus($status),
            'settlement' => PaymentStatus::PAID,
            'pending' => PaymentStatus::PENDING,
            'deny', 'failure' => PaymentStatus::FAILED,
            'expire' => PaymentStatus::EXPIRED,
            'cancel' => PaymentStatus::CANCELLED,
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => PaymentStatus::REFUNDED,
            default => null,
        };
    }

    /**
     * Kartu dengan `fraud_status = challenge` belum lunas; jangan dikonfirmasi.
     *
     * @param  array<string, mixed>  $status
     */
    private function captureStatus(array $status): PaymentStatus
    {
        return match ($status['fraud_status'] ?? null) {
            'accept' => PaymentStatus::PAID,
            'challenge' => PaymentStatus::PENDING,
            default => PaymentStatus::PENDING,
        };
    }

    /**
     * Terapkan status target beserta efek sampingnya pada booking dan tiket.
     *
     * @param  array<string, mixed>  $status
     */
    private function applyTarget(Payment $payment, PaymentStatus $target, array $status): void
    {
        $payment->forceFill([
            'status' => $target,
            'provider_transaction_id' => $status['transaction_id'] ?? $payment->provider_transaction_id,
            'payment_type' => $status['payment_type'] ?? $payment->payment_type,
            'last_response' => $status,
            'paid_at' => $target === PaymentStatus::PAID ? ($payment->paid_at ?? now()) : $payment->paid_at,
        ])->save();

        match ($target) {
            PaymentStatus::PAID => $this->confirmBooking($payment),
            PaymentStatus::REFUNDED => $this->refundBooking($payment),
            default => null,
        };
    }

    /**
     * BR-07: booking `confirmed` + kuota pindah ke `sold` + tiket terbit,
     * semuanya dalam transaksi yang sama.
     *
     * BR-23: bila booking sudah `expired`, coba pesan ulang kuota. Berhasil →
     * konfirmasi; gagal → booking tetap `expired`, payment tetap `paid`, dan
     * masuk antrean anomali untuk refund manual.
     */
    private function confirmBooking(Payment $payment): void
    {
        /** @var Booking $booking */
        $booking = Booking::query()->whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();

        if ($booking->status === BookingStatus::CONFIRMED) {
            // Konfirmasi berulang: tiket sudah ada, penerbit idempoten.
            $this->issuer->issueFor($booking);

            return;
        }

        $quantity = (int) DB::table('booking_items')->where('booking_id', $booking->id)->sum('quantity');
        $slot = VisitSlot::query()->whereKey($booking->visit_slot_id)->lockForUpdate()->firstOrFail();

        if ($booking->status === BookingStatus::PENDING_PAYMENT) {
            // Kuota sudah ditahan sejak booking dibuat.
            $this->quota->confirm($slot, $quantity);
        } elseif ($booking->status === BookingStatus::EXPIRED || $booking->status === BookingStatus::CANCELLED) {
            // BR-23: kuota sudah dilepas; coba tahan ulang.
            if (! $this->quota->reserve($slot, $quantity)) {
                $payment->forceFill([
                    'needs_review' => true,
                    'review_note' => 'Pembayaran masuk setelah booking kedaluwarsa dan kuota tidak tersedia (BR-23).',
                ])->save();

                Log::warning('payment.late_payment_no_quota', [
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id,
                    'quantity' => $quantity,
                ]);

                ActivityLogger::log('payment.needs_review', $payment, [
                    'reason' => 'late_payment_no_quota',
                    'booking_id' => $booking->id,
                ]);

                return;
            }

            $this->quota->confirm($slot, $quantity);
        } else {
            // `refunded` tidak boleh kembali ke `confirmed`.
            return;
        }

        $booking->forceFill([
            'status' => BookingStatus::CONFIRMED,
            'confirmed_at' => now(),
        ])->save();

        $this->issuer->issueFor($booking);

        ActivityLogger::log('booking.confirmed', $booking, [
            'payment_id' => $payment->id,
            'quantity' => $quantity,
        ], $booking->user_id);
    }

    /**
     * Refund/chargeback: booking `refunded`, tiket yang belum dipakai di-void.
     *
     * Kuota tidak dikembalikan otomatis karena refund ditangani manual oleh
     * Admin (FR-PAY-08).
     */
    private function refundBooking(Payment $payment): void
    {
        /** @var Booking $booking */
        $booking = Booking::query()->whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();

        if ($booking->status !== BookingStatus::CONFIRMED) {
            $payment->forceFill([
                'needs_review' => true,
                'review_note' => 'Refund diterima untuk booking yang tidak berstatus confirmed.',
            ])->save();

            return;
        }

        $booking->forceFill(['status' => BookingStatus::REFUNDED])->save();

        Ticket::query()
            ->where('booking_id', $booking->id)
            ->where('status', TicketStatus::ACTIVE->value)
            ->update([
                'status' => TicketStatus::VOID->value,
                'updated_at' => now(),
            ]);

        ActivityLogger::log('booking.refunded', $booking, [
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * BR-11: bandingkan `gross_amount` gateway dengan tagihan tersimpan.
     *
     * Midtrans mengirim "150000.00"; dibandingkan sebagai integer rupiah agar
     * tidak ada pembulatan float.
     *
     * @param  array<string, mixed>  $status
     */
    private function amountsMatch(array $status, Payment $payment): bool
    {
        $raw = $status['gross_amount'] ?? null;

        if (! is_numeric($raw)) {
            return false;
        }

        return (int) round((float) $raw) === (int) $payment->gross_amount;
    }
}
