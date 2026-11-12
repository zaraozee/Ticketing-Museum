<?php

namespace App\Mail;

use App\Models\EmailLog;
use App\Services\Notification\EmailLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dasar email transaksional (FR-NTF-01).
 *
 * Semua email dikirim lewat antrean agar request pengguna tidak menunggu
 * SMTP. Setiap percobaan pengiriman tercatat di `email_logs`; kegagalan
 * permanen ditandai `failed` beserta pesan error, bukan hilang diam-diam.
 */
abstract class TransactionalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Cukup tiga percobaan: kegagalan SMTP yang berulang biasanya berarti
     * kredensial atau reputasi pengirim bermasalah, bukan gangguan sesaat.
     */
    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    /**
     * Baris `email_logs` yang harus diperbarui. Disimpan sebagai id agar
     * mailable tetap dapat diserialisasi ke antrean.
     */
    public ?int $emailLogId = null;

    /**
     * Subjek email; wajib memakai nama aplikasi dari konfigurasi.
     */
    abstract protected function subjectLine(): string;

    public function send($mailer): ?SentMessage
    {
        $log = $this->emailLog();

        EmailLogger::attempted($log);

        try {
            $result = parent::send($mailer);

            EmailLogger::sent($log);

            return $result;
        } catch (Throwable $exception) {
            EmailLogger::failed($log, $exception);

            throw $exception;
        }
    }

    /**
     * Dipanggil Laravel saat job email gagal permanen (seluruh percobaan habis).
     */
    public function failed(Throwable $exception): void
    {
        EmailLogger::failed($this->emailLog(), $exception);
    }

    private function emailLog(): ?EmailLog
    {
        return $this->emailLogId === null
            ? null
            : EmailLog::query()->find($this->emailLogId);
    }
}
