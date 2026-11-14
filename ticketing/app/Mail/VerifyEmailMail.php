<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Notification\EmailLogger;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Tautan verifikasi alamat email (FR-AUTH-02, FR-NTF-01).
 *
 * Memakai `TransactionalMail` agar pengiriman lewat antrean dan setiap
 * percobaan tercatat di `email_logs` — sama seperti email transaksional lain.
 * Notifikasi bawaan Laravel tidak melakukan keduanya.
 */
class VerifyEmailMail extends TransactionalMail
{
    public function __construct(public User $user, public string $verificationUrl) {}

    /**
     * Buat mailable sekaligus mencatat antreannya di `email_logs`.
     */
    public static function forUser(User $user): self
    {
        $mail = new self($user, self::verificationUrlFor($user));

        $log = EmailLogger::queued(self::class, [
            'subject' => $mail->subjectLine(),
            'to' => $user->email,
            'user_id' => $user->id,
            'mailable' => $user,
        ]);

        $mail->emailLogId = $log->id;

        return $mail;
    }

    /**
     * Tautan bertanda tangan yang hanya berlaku selama masa berlaku terverifikasi.
     */
    public static function verificationUrlFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(self::expiryMinutes()),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }

    /**
     * Masa berlaku tautan, dibaca dari konfigurasi agar email dan pengujian
     * memakai angka yang sama.
     */
    public static function expiryMinutes(): int
    {
        return (int) config('auth.verification.expire', 60);
    }

    protected function subjectLine(): string
    {
        return 'Verifikasi email Anda — '.config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.verify-email',
            with: [
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl,
                'expiresInMinutes' => self::expiryMinutes(),
                'appName' => config('app.name'),
            ],
        );
    }
}
