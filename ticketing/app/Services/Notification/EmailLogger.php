<?php

namespace App\Services\Notification;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Pencatatan pengiriman email transaksional (FR-NTF-01, NFR-13).
 *
 * Satu baris `email_logs` dibuat **sebelum** email masuk antrean, sehingga
 * email yang gagal terkirim tetap terlihat sebagai `pending`/`failed` dan
 * dapat ditelusuri saat pengguna melapor "email tidak sampai".
 */
class EmailLogger
{
    /**
     * Catat email yang baru masuk antrean.
     *
     * @param  array{subject: string, to: string, user_id?: int|null, mailable?: Model|null}  $attributes
     */
    public static function queued(string $mailableClass, array $attributes): EmailLog
    {
        $mailable = $attributes['mailable'] ?? null;

        return EmailLog::create([
            'user_id' => $attributes['user_id'] ?? null,
            'mailable_type' => $mailableClass,
            'mailable_id' => $mailable?->getKey(),
            'subject' => mb_substr($attributes['subject'], 0, 200),
            'to_email' => mb_substr($attributes['to'], 0, 190),
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    /**
     * Tandai satu percobaan pengiriman (dipanggil setiap kali job berjalan).
     */
    public static function attempted(?EmailLog $log): void
    {
        if ($log === null) {
            return;
        }

        $log->increment('attempts');
    }

    public static function sent(?EmailLog $log): void
    {
        if ($log === null) {
            return;
        }

        $log->forceFill([
            'status' => 'sent',
            'error_message' => null,
        ])->save();
    }

    /**
     * Tandai gagal. Pesan error dipotong agar tidak memenuhi kolom dan tidak
     * pernah memuat isi email.
     */
    public static function failed(?EmailLog $log, Throwable $exception): void
    {
        if ($log === null) {
            return;
        }

        $log->forceFill([
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 500),
        ])->save();
    }
}
