<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = Ticket::query()
            ->whereHas('booking', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['booking.museum', 'booking.visitSlot'])
            ->latest('created_at')
            ->paginate(15)
            ->through(fn (Ticket $ticket): array => [
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'ticket_code' => $ticket->ticket_code,
                'museum_name' => $ticket->booking->museum->name,
                'visit_date' => $ticket->booking->visit_date_snapshot->format('d/m/Y'),
                'slot_label' => $this->slotLabel($ticket),
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'booking_code' => $ticket->booking->booking_code,
            ]);

        return Inertia::render('Public/Tickets/Index', [
            'tickets' => $tickets,
        ]);
    }

    public function show(Request $request, string $uuid): Response
    {
        $ticket = Ticket::where('uuid', $uuid)
            ->whereHas('booking', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['booking.museum', 'booking.visitSlot', 'bookingItem.ticketType'])
            ->firstOrFail();

        return Inertia::render('Public/Tickets/Show', [
            'ticket' => [
                'uuid' => $ticket->uuid,
                'ticket_code' => $ticket->ticket_code,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'museum_name' => $ticket->booking->museum->name,
                'museum_address' => $ticket->booking->museum->address,
                'museum_city' => $ticket->booking->museum->city,
                'visit_date' => $ticket->booking->visit_date_snapshot->format('d/m/Y'),
                'slot_label' => $this->slotLabel($ticket),
                'ticket_type_name' => $ticket->bookingItem->ticket_name_snapshot,
                'booking_code' => $ticket->booking->booking_code,
                'contact_name' => $ticket->booking->contact_name,
                'contact_email' => $ticket->booking->contact_email,
                'used_at' => $ticket->used_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Label jam kunjungan; slot harian (tanpa jam) ditampilkan "Seharian".
     */
    private function slotLabel(Ticket $ticket): string
    {
        $slot = $ticket->booking->visitSlot;

        if ($slot->start_time === null || $slot->end_time === null) {
            return 'Seharian';
        }

        return substr((string) $slot->start_time, 0, 5).' - '.substr((string) $slot->end_time, 0, 5);
    }
}
