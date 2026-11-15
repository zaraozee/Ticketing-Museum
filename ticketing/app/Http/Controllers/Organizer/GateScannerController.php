<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Museum;
use App\Models\TicketScan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    /**
     * Museum yang boleh dipindai pengguna ini: miliknya sendiri (organizer)
     * digabung dengan museum tempat ia terdaftar sebagai staf aktif.
     *
     * Keduanya harus tetap berupa koleksi **model** sampai `merge()` selesai:
     * `Eloquent\Collection::merge()` memanggil `getKey()` pada tiap item,
     * sehingga memetakan ke array lebih dulu menghasilkan
     * "Call to a member function getKey() on array". Pemetaan ke bentuk yang
     * dipakai frontend dilakukan setelah penggabungan.
     *
     * @return Collection<int, array{id: int, name: string, slug: string}>
     */
    private function getAccessibleMuseums(User $user): Collection
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
            ->values();

        return $orgMuseums->merge($staffMuseums)
            ->unique('id')
            ->values()
            ->map(fn (Museum $museum): array => [
                'id' => $museum->id,
                'name' => $museum->name,
                'slug' => $museum->slug,
            ])
            ->values();
    }
}
