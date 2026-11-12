<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Booking\BookingService;
use Illuminate\Console\Command;

/**
 * Menandai booking `pending_payment` yang melewati batas waktu sebagai
 * `expired` dan melepas kuotanya (FR-PAY-06, BR-06).
 *
 * Dijalankan berkala lewat scheduler. Idempoten: guard status di
 * `BookingService::expire()` memastikan kuota tidak dilepas dua kali.
 */
class ExpireBookingsCommand extends Command
{
    protected $signature = 'bookings:expire {--limit=500 : Jumlah maksimum booking yang diproses per jalan}';

    protected $description = 'Kedaluwarsakan booking pending_payment yang melewati batas waktu dan lepas kuotanya';

    public function handle(BookingService $bookings): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $expired = 0;

        Booking::query()
            ->expiredCandidate()
            ->orderBy('expires_at')
            ->limit($limit)
            ->get()
            ->each(function (Booking $booking) use ($bookings, &$expired): void {
                if ($bookings->expire($booking)) {
                    $expired++;
                }
            });

        $this->info("Booking kedaluwarsa: {$expired}");

        return self::SUCCESS;
    }
}
