<?php

namespace App\Services\Slot;

use App\Enums\BookingMode;
use App\Models\Museum;
use App\Models\SlotCapacityOverride;
use App\Models\SlotTemplate;
use App\Models\VisitSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pembuatan baris `visit_slots` dari template (FR-ORG-07, D-28).
 *
 * Sifat penting:
 * - **Idempoten**: memanggil ulang untuk rentang tanggal yang sama tidak
 *   menggandakan slot (AC-1 Fase 4).
 * - **Menghormati hari tutup**: tanggal tutup museum dan hari yang
 *   operasionalnya `is_closed` tidak menghasilkan slot (AC-2).
 * - **Tidak menurunkan kuota**: slot yang sudah ada tidak pernah diturunkan
 *   kapasitasnya di bawah `reserved_count + sold_count` (BR-20).
 * - **Menghormati override**: kapasitas dari `slot_capacity_overrides`
 *   dipakai bila ada (D-27).
 */
class SlotGenerator
{
    /**
     * Hasil satu kali penjalanan generator.
     */
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /**
     * Buat slot untuk rentang tanggal [from, to] (inklusif).
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function generate(Museum $museum, Carbon $from, Carbon $to): array
    {
        $this->created = 0;
        $this->updated = 0;
        $this->skipped = 0;

        $templates = SlotTemplate::query()
            ->where('museum_id', $museum->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            return $this->result();
        }

        $openDays = $museum->operatingHours()
            ->where('is_closed', false)
            ->pluck('day_of_week')
            ->map(fn ($day): int => (int) $day)
            ->all();

        $closedDates = $museum->closedDates()
            ->whereBetween('closed_on', [$from->toDateString(), $to->toDateString()])
            ->pluck('closed_on')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $overrides = SlotCapacityOverride::query()
            ->where('museum_id', $museum->id)
            ->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $cursor = $from->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($to)) {
            $dateString = $cursor->toDateString();
            $dayOfWeek = (int) $cursor->dayOfWeek;

            // AC-2: hari tutup mingguan dan tanggal tutup tidak menghasilkan slot.
            $isClosedDay = ! in_array($dayOfWeek, $openDays, true);
            $isClosedDate = in_array($dateString, $closedDates, true);

            if ($isClosedDay || $isClosedDate) {
                $this->skipped += $templates->count();
                $cursor->addDay();

                continue;
            }

            foreach ($templates as $template) {
                $capacity = $this->resolveCapacity($museum, $template, $overrides, $dateString);

                $this->upsertSlot($museum, $template, $dateString, $capacity);
            }

            $cursor->addDay();
        }

        return $this->result();
    }

    /**
     * Kapasitas efektif: override per tanggal bila ada, jika tidak pakai template.
     *
     * @param  Collection<int, SlotCapacityOverride>  $overrides
     */
    private function resolveCapacity(
        Museum $museum,
        SlotTemplate $template,
        $overrides,
        string $dateString,
    ): int {
        $match = $overrides->first(function (SlotCapacityOverride $override) use ($template, $dateString): bool {
            if ($override->visit_date->toDateString() !== $dateString) {
                return false;
            }

            $overrideStart = $override->start_time === null
                ? null
                : substr((string) $override->start_time, 0, 8);
            $templateStart = $template->start_time === null
                ? null
                : substr((string) $template->start_time, 0, 8);

            return $overrideStart === $templateStart;
        });

        return $match instanceof SlotCapacityOverride
            ? $match->capacity
            : $template->capacity;
    }

    /**
     * Buat atau perbarui satu slot tanpa pernah menurunkan kuota di bawah
     * `reserved_count + sold_count` (BR-20).
     */
    private function upsertSlot(
        Museum $museum,
        SlotTemplate $template,
        string $dateString,
        int $capacity,
    ): void {
        DB::transaction(function () use ($museum, $template, $dateString, $capacity): void {
            $existing = VisitSlot::query()
                ->where('museum_id', $museum->id)
                ->where('visit_date', $dateString)
                ->when(
                    $template->start_time === null,
                    fn ($query) => $query->whereNull('start_time'),
                    fn ($query) => $query->where('start_time', $template->start_time),
                )
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                VisitSlot::create([
                    'museum_id' => $museum->id,
                    'visit_date' => $dateString,
                    'start_time' => $template->start_time,
                    'end_time' => $template->end_time,
                    'capacity' => $capacity,
                    'reserved_count' => 0,
                    'sold_count' => 0,
                    'status' => 'open',
                ]);

                $this->created++;

                return;
            }

            $committed = $existing->reserved_count + $existing->sold_count;

            // BR-20: jangan pernah menurunkan kapasitas di bawah kuota terpakai.
            if ($capacity < $committed) {
                $this->skipped++;

                return;
            }

            if ($existing->capacity === $capacity
                && $existing->end_time === $template->end_time
                && $existing->status->value === 'open') {
                // Sudah sesuai — tidak ada yang perlu diubah (idempoten).
                $this->skipped++;

                return;
            }

            $existing->update([
                'capacity' => $capacity,
                'end_time' => $template->end_time,
            ]);

            $this->updated++;
        });
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    private function result(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ];
    }

    /**
     * Museum mode `daily` memakai satu slot per hari (start_time null).
     */
    public function isDaily(Museum $museum): bool
    {
        return $museum->booking_mode === BookingMode::DAILY;
    }
}
