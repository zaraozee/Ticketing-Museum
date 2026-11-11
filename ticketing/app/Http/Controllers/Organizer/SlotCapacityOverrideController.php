<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\StoreSlotCapacityOverrideRequest;
use App\Models\Museum;
use App\Models\SlotCapacityOverride;
use App\Models\VisitSlot;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Override kuota per tanggal (FR-ORG-07, D-27).
 *
 * Override disimpan terpisah agar `slots:generate` tidak menimpanya, dan
 * langsung diterapkan ke slot yang sudah ada bila kapasitasnya masih aman
 * (BR-20).
 */
class SlotCapacityOverrideController extends Controller
{
    public function store(StoreSlotCapacityOverrideRequest $request, Museum $museum): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);

        $validated = $request->validated();
        $date = (string) $validated['visit_date'];
        $startTime = $this->normalizeTime($validated['start_time'] ?? null);
        $capacity = (int) $validated['capacity'];

        // BR-20: override tidak boleh lebih kecil dari kuota yang sudah terpakai
        // pada slot yang bersangkutan.
        $slot = $this->findSlot($museum, $date, $startTime);

        if ($slot !== null) {
            $committed = $slot->reserved_count + $slot->sold_count;

            if ($capacity < $committed) {
                return back()->withErrors([
                    'capacity' => sprintf(
                        'Kuota override tidak boleh di bawah %d (terjual + ditahan) pada slot tersebut.',
                        $committed,
                    ),
                ]);
            }
        }

        DB::transaction(function () use ($museum, $date, $startTime, $capacity, $validated, $slot): void {
            SlotCapacityOverride::updateOrCreate(
                [
                    'museum_id' => $museum->id,
                    'visit_date' => $date,
                    'start_time' => $startTime,
                ],
                [
                    'capacity' => $capacity,
                    'note' => $validated['note'] ?? null,
                ],
            );

            if ($slot !== null) {
                $slot->update(['capacity' => $capacity]);
            }
        });

        ActivityLogger::log('slot_override.created', $museum, [
            'museum_id' => $museum->id,
            'visit_date' => $date,
            'start_time' => $startTime,
            'capacity' => $capacity,
        ]);

        return back()->with('success', 'Override kuota disimpan.');
    }

    public function destroy(Museum $museum, SlotCapacityOverride $override): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);
        abort_unless((int) $override->museum_id === (int) $museum->id, 404);

        $override->delete();

        ActivityLogger::log('slot_override.deleted', $museum, [
            'museum_id' => $museum->id,
            'visit_date' => $override->visit_date->toDateString(),
        ]);

        return back()->with('success', 'Override kuota dihapus. Jalankan pembuatan slot untuk menerapkan kuota template.');
    }

    private function findSlot(Museum $museum, string $date, ?string $startTime): ?VisitSlot
    {
        return VisitSlot::query()
            ->where('museum_id', $museum->id)
            ->where('visit_date', $date)
            ->when(
                $startTime === null,
                fn ($query) => $query->whereNull('start_time'),
                fn ($query) => $query->where('start_time', $startTime),
            )
            ->first();
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 8).(strlen((string) $value) === 5 ? ':00' : '');
    }
}
