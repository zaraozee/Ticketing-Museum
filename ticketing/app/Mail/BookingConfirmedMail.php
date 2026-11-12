<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Ticket;
use App\Services\Notification\EmailLogger;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-ticket setelah pembayaran terkonfirmasi (FR-TIX-04, FR-NTF-01).
 *
 * QR **tidak** dilampirkan sebagai gambar inline karena banyak klien email
 * memblokirnya (THIRD_PARTY §3). Email memuat tautan "Buka tiket" ke halaman
 * e-ticket, dan tautan itu selalu dapat dibuka ulang dari akun.
 */
class BookingConfirmedMail extends TransactionalMail
{
    public function __construct(public Booking $booking) {}

    /**
     * Buat mailable sekaligus mencatat antreannya di `email_logs`.
     */
    public static function forBooking(Booking $booking): self
    {
        $mail = new self($booking);

        $log = EmailLogger::queued(self::class, [
            'subject' => $mail->subjectLine(),
            'to' => $booking->contact_email,
            'user_id' => $booking->user_id,
            'mailable' => $booking,
        ]);

        $mail->emailLogId = $log->id;

        return $mail;
    }

    protected function subjectLine(): string
    {
        return 'E-ticket '.$this->booking->booking_code.' — '.config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        $tickets = Ticket::query()
            ->where('booking_id', $this->booking->id)
            ->orderBy('id')
            ->get();

        return new Content(
            markdown: 'mail.booking-confirmed',
            with: [
                'booking' => $this->booking,
                'tickets' => $tickets,
                // Tautan langsung ke tiket pertama; halaman e-ticket memuat
                // QR yang dapat dibuka ulang kapan saja dari akun.
                'ticketUrl' => $tickets->isEmpty()
                    ? route('tickets.index')
                    : route('tickets.show', $tickets->first()->uuid),
                'appName' => config('app.name'),
            ],
        );
    }
}
