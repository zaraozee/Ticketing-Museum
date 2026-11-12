<?php

namespace App\Services\Notification;

use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;

/**
 * Notifikasi setelah booking terkonfirmasi (FR-NTF-01, FR-NTF-02, FR-TIX-04).
 *
 * Dipanggil **setelah** transaksi konfirmasi commit. Bila pengiriman email
 * gagal, booking dan tiket tetap sah: pengguna masih dapat membuka e-ticket
 * dari akunnya, dan kegagalan tercatat di `email_logs`.
 */
class BookingNotifier
{
    /**
     * Kirim email e-ticket dan buat notifikasi in-app.
     */
    public function confirmed(Booking $booking): void
    {
        Mail::to($booking->contact_email)->queue(BookingConfirmedMail::forBooking($booking));

        $this->notifyInApp(
            $booking,
            'booking.confirmed',
            'Pembayaran diterima',
            'E-ticket untuk '.$booking->museum_name_snapshot.' sudah terbit.',
        );
    }

    /**
     * Beri tahu pengguna bahwa booking kedaluwarsa (FR-PAY-06).
     */
    public function expired(Booking $booking): void
    {
        $this->notifyInApp(
            $booking,
            'booking.expired',
            'Pemesanan kedaluwarsa',
            'Pemesanan '.$booking->booking_code.' dibatalkan karena pembayaran tidak diterima tepat waktu.',
        );
    }

    /**
     * Beri tahu pengguna bahwa pembayaran sedang ditinjau admin (BR-23).
     */
    public function needsReview(Booking $booking, string $reason): void
    {
        $this->notifyInApp(
            $booking,
            'payment.review',
            'Pembayaran sedang ditinjau',
            $reason,
        );
    }

    /**
     * Buat notifikasi in-app. Idempoten per (booking, jenis) agar webhook
     * duplikat tidak menumpuk notifikasi yang sama.
     */
    private function notifyInApp(Booking $booking, string $type, string $title, string $body): void
    {
        $alreadyExists = Notification::query()
            ->where('user_id', $booking->user_id)
            ->where('type', $type)
            ->where('data->booking_id', $booking->id)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        Notification::create([
            'user_id' => $booking->user_id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'url' => route('bookings.show', $booking->uuid),
            ],
        ]);
    }
}
