<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Museum;
use App\Models\SlotCapacityOverride;
use App\Models\SlotTemplate;
use App\Models\VisitSlot;
use App\Services\Audit\ActivityLogger;
use App\Services\Slot\SlotGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman jadwal & kuota museum (FR-ORG-07).
 *
 * Menampilkan template slot, kalender slot satu bulan, dan override kuota.
 * Pembuatan slot dijalankan manual lewat tombol (selain jadwal harian).
 */
class ScheduleController extends Controller
{
    public function __construct(private readonly SlotGenerator $generator) {}

    public function index(Request $request, Museum $museum): Response
    {
        Gate::authorize('manageContent', $museum);

        $month = $this->resolveMonth($request);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $slots = VisitSlot::query()
            ->where('museum_id', $museum->id)
            ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('visit_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (VisitSlot $slot): array => [
                'id' => $slot->id,
                'visit_date' => $slot->visit_date->toDateString(),
                'label' => $slot->label(),
                'capacity' => $slot->capacity,
                'reserved_count' => $slot->reserved_count,
                'sold_count' => $slot->sold_count,
                'remaining' => $slot->remaining(),
                'status' => $slot->status->value,
                'status_label' => $slot->status->label(),
                'availability' => $slot->availability()->value,
                'availability_label' => $slot->availability()->label(),
            ])
            ->values();

        return Inertia::render('Organizer/Museums/Schedule', [
            'museum' => [
                'slug' => $museum->slug,
                'name' => $museum->name,
                'booking_mode' => $museum->booking_mode->value,
                'booking_mode_label' => $museum->booking_mode->label(),
            ],
            'month' => $month->format('Y-m'),
            'slots' => $slots,
            'templates' => SlotTemplate::query()
                ->where('museum_id', $museum->id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SlotTemplate $template): array => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'label' => $template->label(),
                    'start_time' => $template->start_time === null ? null : substr((string) $template->start_time, 0, 5),
                    'end_time' => $template->end_time === null ? null : substr((string) $template->end_time, 0, 5),
                    'capacity' => $template->capacity,
                    'is_active' => $template->is_active,
                ])
                ->values(),
            'overrides' => SlotCapacityOverride::query()
                ->where('museum_id', $museum->id)
                ->where('visit_date', '>=', now()->toDateString())
                ->orderBy('visit_date')
                ->get()
                ->map(fn (SlotCapacityOverride $override): array => [
                    'id' => $override->id,
                    'visit_date' => $override->visit_date->toDateString(),
                    'start_time' => $override->start_time === null ? null : substr((string) $override->start_time, 0, 5),
                    'capacity' => $override->capacity,
                    'note' => $override->note,
                ])
                ->values(),
        ]);
    }

    /**
     * Jalankan generator untuk bulan yang sedang dilihat (idempoten).
     */
    public function generate(Request $request, Museum $museum): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);

        $month = $this->resolveMonth($request);

        $result = $this->generator->generate(
            $museum,
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        );

        ActivityLogger::log('visit_slot.generated', $museum, [
            'museum_id' => $museum->id,
            'month' => $month->format('Y-m'),
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);

        return back()->with('success', sprintf(
            'Pembuatan slot selesai: %d dibuat, %d diperbarui, %d dilewati.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));
    }

    /**
     * Ubah kapasitas satu slot dengan menghormati BR-20.
     */
    public function updateSlotCapacity(Request $request, Museum $museum, VisitSlot $slot): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);
        abort_unless((int) $slot->museum_id === (int) $museum->id, 404);

        $validated = $request->validate([
            'capacity' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $capacity = (int) $validated['capacity'];
        $committed = $slot->reserved_count + $slot->sold_count;

        if ($capacity < $committed) {
            // BR-20: tolak dengan peringatan yang jelas, bukan error 500.
            return back()->withErrors([
                'capacity' => sprintf(
                    'Kuota tidak boleh diturunkan di bawah %d (terjual + ditahan).',
                    $committed,
                ),
            ]);
        }

        $before = $slot->capacity;
        $slot->update(['capacity' => $capacity]);

        ActivityLogger::log('visit_slot.capacity_updated', $slot, [
            'museum_id' => $museum->id,
            'before' => $before,
            'after' => $capacity,
        ]);

        return back()->with('success', 'Kuota slot diperbarui.');
    }

    private function resolveMonth(Request $request): Carbon
    {
        $raw = $request->string('month')->toString();

        if (preg_match('/^\d{4}-\d{2}$/', $raw) === 1) {
            return Carbon::createFromFormat('Y-m-d', $raw.'-01')->startOfMonth();
        }

        return Carbon::today()->startOfMonth();
    }
}
