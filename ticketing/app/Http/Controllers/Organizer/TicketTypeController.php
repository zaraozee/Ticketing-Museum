<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\StoreTicketTypeRequest;
use App\Models\Museum;
use App\Models\TicketType;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kelola jenis tiket dan harga (FR-ORG-06).
 *
 * Harga disimpan sebagai integer rupiah; perubahan harga tidak mengubah
 * booking lama karena snapshot di `booking_items` (BR-19).
 */
class TicketTypeController extends Controller
{
    /**
     * Halaman kelola jenis tiket sebuah museum (FR-ORG-06).
     */
    public function index(Museum $museum): Response
    {
        Gate::authorize('manageContent', $museum);

        return Inertia::render('Organizer/Museums/Tickets', [
            'museum' => [
                'slug' => $museum->slug,
                'name' => $museum->name,
                'status' => $museum->status->value,
                'status_label' => $museum->status->label(),
            ],
            'ticketTypes' => $museum->ticketTypes()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (TicketType $ticketType): array => [
                    'id' => $ticketType->id,
                    'name' => $ticketType->name,
                    'description' => $ticketType->description,
                    'price' => $ticketType->price,
                    'price_label' => $ticketType->formattedPrice(),
                    'min_age' => $ticketType->min_age,
                    'max_age' => $ticketType->max_age,
                    'is_active' => $ticketType->is_active,
                    'sort_order' => $ticketType->sort_order,
                ])
                ->values(),
        ]);
    }

    public function store(StoreTicketTypeRequest $request, Museum $museum): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);

        $data = $request->validated();
        $data['museum_id'] = $museum->id;
        $data['sort_order'] = $data['sort_order'] ?? ((int) $museum->ticketTypes()->max('sort_order') + 1);
        $data['is_active'] = $request->boolean('is_active', true);

        $ticketType = TicketType::create($data);

        ActivityLogger::log('ticket_type.created', $ticketType, [
            'museum_id' => $museum->id,
            'name' => $ticketType->name,
            'price' => $ticketType->price,
        ]);

        return back()->with('success', 'Jenis tiket ditambahkan.');
    }

    public function update(StoreTicketTypeRequest $request, Museum $museum, TicketType $ticketType): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);
        $this->ensureBelongsTo($museum, $ticketType);

        $before = $ticketType->only(['name', 'price']);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $ticketType->is_active);

        $ticketType->update($data);

        ActivityLogger::log('ticket_type.updated', $ticketType, [
            'museum_id' => $museum->id,
            'before' => $before,
            'after' => $ticketType->only(['name', 'price']),
        ]);

        return back()->with('success', 'Jenis tiket diperbarui.');
    }

    /**
     * Soft delete: jenis tiket masih direferensikan booking lama (ERD §1).
     */
    public function destroy(Museum $museum, TicketType $ticketType): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);
        $this->ensureBelongsTo($museum, $ticketType);

        $ticketType->delete();

        ActivityLogger::log('ticket_type.deleted', $ticketType, [
            'museum_id' => $museum->id,
            'name' => $ticketType->name,
        ]);

        return back()->with('success', 'Jenis tiket dihapus.');
    }

    private function ensureBelongsTo(Museum $museum, TicketType $ticketType): void
    {
        abort_unless((int) $ticketType->museum_id === (int) $museum->id, 404);
    }
}
