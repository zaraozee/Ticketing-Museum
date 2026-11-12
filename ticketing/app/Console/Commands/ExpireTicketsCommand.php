<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tandai tiket `active` menjadi `expired` setelah tanggal kunjungan lewat.
 *
 * Dijadwalkan harian (misal jam 02:00).
 */
class ExpireTicketsCommand extends Command
{
    protected $signature = 'tickets:expire';

    protected $description = 'Mark active tickets as expired after their visit date has passed';

    public function handle(): int
    {
        $expired = DB::transaction(function () {
            return Ticket::whereHas('bookingItem.booking.visitSlot', function ($q) {
                $q->where('visit_date', '<', now()->toDateString());
            })
                ->where('status', TicketStatus::ACTIVE->value)
                ->update([
                    'status' => TicketStatus::EXPIRED->value,
                    'updated_at' => now(),
                ]);
        });

        $this->info("Ditandai {$expired} tiket sebagai expired.");

        return self::SUCCESS;
    }
}
