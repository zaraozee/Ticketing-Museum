<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\VisitReminderMail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim pengingat kunjungan H-1 (FR-NTF-03).
 *
 * Dijalankan sekali sehari. Penjaga duplikasi memakai `reminder_sent_at`
 * sehingga menjalankan ulang command ini — misalnya setelah server restart
 * atau saat menguji manual — tidak mengirim email kedua.
 */
class SendVisitRemindersCommand extends Command
{
    protected $signature = 'bookings:remind {--dry-run : Tampilkan jumlah tanpa mengirim email}';

    protected $description = 'Kirim email pengingat untuk kunjungan besok (FR-NTF-03)';

    public function handle(): int
    {
        $visitDate = now()->addDay()->toDateString();

        $query = Booking::query()
            ->where('status', BookingStatus::CONFIRMED->value)
            ->whereDate('visit_date_snapshot', $visitDate)
            ->whereNull('reminder_sent_at');

        $total = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$total} booking akan diingatkan untuk kunjungan {$visitDate}.");

            return self::SUCCESS;
        }

        $sent = 0;

        $query->chunkById(100, function ($bookings) use (&$sent): void {
            foreach ($bookings as $booking) {
                Mail::to($booking->contact_email)->queue(VisitReminderMail::forBooking($booking));

                // Ditandai setelah masuk antrean: bila proses berhenti di
                // tengah, booking yang belum diproses tetap akan diingatkan
                // pada eksekusi berikutnya.
                $booking->forceFill(['reminder_sent_at' => now()])->save();

                $sent++;
            }
        });

        $this->info("{$sent} email pengingat diantrekan untuk kunjungan {$visitDate}.");

        return self::SUCCESS;
    }
}
