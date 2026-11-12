<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\Notification\EmailLogger;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Pengingat kunjungan H-1 (FR-NTF-03).
 *
 * Dikirim sekali per booking. Penjaga duplikasi ada di `reminder_sent_at`
 * pada tabel `bookings`, bukan di mailable, karena command penjadwal dapat
 * berjalan lebih dari sekali dalam sehari (mis. setelah server restart).
 */
class VisitReminderMail extends TransactionalMail
{
    public function __construct(public Booking $booking) {}

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
        return 'Pengingat kunjungan besok — '.$this->booking->museum_name_snapshot;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.visit-reminder',
            with: [
                'booking' => $this->booking,
                'ticketUrl' => route('tickets.index'),
                'appName' => config('app.name'),
            ],
        );
    }
}
