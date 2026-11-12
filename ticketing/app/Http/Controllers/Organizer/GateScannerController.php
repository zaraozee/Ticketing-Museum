<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Museum;
use App\Models\TicketScan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GateScannerController extends Controller
{
    public function scanner(Request $request): Response
    {
        $user = $request->user();

        $museums = $this->getAccessibleMuseums($user);

        return Inertia::render('Organizer/Gate/Scanner', [
            'museums' => $museums,
        ]);
    }

    public function history(Request $request): Response
    {
        $user = $request->user();
        $museums = $this->getAccessibleMuseums($user);
        $museumId = $request->get('museum_id', $museums[0]['id'] ?? null);

        if (! $museumId) {
            return Inertia::render('Organizer/Gate/History', [
                'museums' => $museums,
                'scans' => [],
                'selectedMuseum' => null,
            ]);
        }

        // Validasi akses
        $accessibleIds = $museums->pluck('id')->toArray();
        if (! in_array((int) $museumId, $accessibleIds)) {
            abort(403);
        }

        $scans = TicketScan::where('museum_id', $museumId)
            ->with(['ticket.bookingItem.booking', 'scannedByUser:id,name'])
            ->orderByDesc('scanned_at')
            ->limit(100)
            ->get()
            ->map(fn ($scan) => [
                'id' => $scan->id,
                'result' => $scan->result->value,
                'label' => $scan->result->label(),
                'color' => $scan->result->color(),
                'scanned_at' => $scan->scanned_at->format('d/m/Y H:i'),
                'scanned_by' => $scan->scannedByUser?->name,
                'ticket_code' => $scan->ticket?->ticket_code,
                'holder' => $scan->ticket?->bookingItem?->booking?->contact_name,
                'notes' => $scan->notes,
            ]);

        return Inertia::render('Organizer/Gate/History', [
            'museums' => $museums,
            'scans' => $scans,
            'selectedMuseum' => (int) $museumId,
        ]);
    }

    private function getAccessibleMuseums($user)
    {
        $orgMuseums = Museum::select('id', 'name', 'slug')
            ->where('organizer_profile_id', $user->organizerProfile?->id)
            ->where('status', 'published')
            ->orderBy('name')
            ->get();

        $staffMuseums = $user->museumStaff()
            ->where('is_active', true)
            ->with('museum:id,name,slug,status')
            ->get()
            ->pluck('museum')
            ->filter(fn ($m) => $m && $m->status->value === 'published')
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'slug' => $m->slug]);

        return $orgMuseums->merge($staffMuseums)->unique('id')->values();
    }
}
