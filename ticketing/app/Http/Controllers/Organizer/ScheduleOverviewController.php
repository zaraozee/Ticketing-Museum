<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Museum;
use App\Models\SlotTemplate;
use App\Models\TicketType;
use App\Models\VisitSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pintu masuk modul "Tiket & Jadwal Kuota" (FR-ORG-06, FR-ORG-07).
 *
 * Menampilkan museum milik organizer beserta ringkasan inventori: jumlah jenis
 * tiket aktif, jumlah template slot, dan sisa kuota terdekat. Detail dikelola
 * di halaman per museum.
 */
class ScheduleOverviewController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Museum::class);

        $user = $request->user();

        $museums = Museum::query()
            ->when(
                ! $user?->isGlobalAdmin(),
                fn ($query) => $query->whereIn(
                    'organizer_profile_id',
                    $user?->organizerProfile === null ? [] : [$user->organizerProfile->id],
                ),
            )
            ->orderBy('name')
            ->get();

        $museumIds = $museums->pluck('id')->all();

        $ticketTypeCounts = TicketType::query()
            ->whereIn('museum_id', $museumIds)
            ->where('is_active', true)
            ->selectRaw('museum_id, COUNT(*) as aggregate')
            ->groupBy('museum_id')
            ->pluck('aggregate', 'museum_id');

        $templateCounts = SlotTemplate::query()
            ->whereIn('museum_id', $museumIds)
            ->where('is_active', true)
            ->selectRaw('museum_id, COUNT(*) as aggregate')
            ->groupBy('museum_id')
            ->pluck('aggregate', 'museum_id');

        $upcoming = VisitSlot::query()
            ->whereIn('museum_id', $museumIds)
            ->where('visit_date', '>=', Carbon::today()->toDateString())
            ->get()
            ->groupBy('museum_id');

        return Inertia::render('Organizer/Schedules/Index', [
            'museums' => $museums->map(function (Museum $museum) use ($upcoming, $ticketTypeCounts, $templateCounts): array {
                $slots = $upcoming->get($museum->id, collect());

                return [
                    'slug' => $museum->slug,
                    'name' => $museum->name,
                    'status' => $museum->status->value,
                    'status_label' => $museum->status->label(),
                    'booking_mode' => $museum->booking_mode->value,
                    'booking_mode_label' => $museum->booking_mode->label(),
                    'active_ticket_types_count' => (int) ($ticketTypeCounts[$museum->id] ?? 0),
                    'active_slot_templates_count' => (int) ($templateCounts[$museum->id] ?? 0),
                    'upcoming_slot_count' => $slots->count(),
                    'upcoming_remaining' => $slots->sum(fn (VisitSlot $slot): int => $slot->remaining()),
                ];
            })->values(),
        ]);
    }
}
