<?php

namespace App\Http\Controllers\Public;

use App\Enums\AvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Models\Museum;
use App\Models\MuseumClosedDate;
use App\Models\MuseumOperatingHour;
use App\Models\TicketType;
use App\Models\VisitSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Endpoint ketersediaan untuk kalender pemesanan (FR-BKG-02..04, AC-5 Fase 4).
 *
 * - `GET /api/v1/museums/{slug}/availability?month=YYYY-MM` → ringkasan per tanggal
 * - `GET /api/v1/museums/{slug}/slots?date=YYYY-MM-DD`      → slot + sisa kuota
 *
 * Hanya museum `published` yang dilayani (BR-01).
 */
class AvailabilityController extends Controller
{
    /**
     * Ringkasan ketersediaan satu bulan untuk kalender.
     */
    public function month(Request $request, Museum $museum): JsonResponse
    {
        abort_unless($museum->status->isPubliclyVisible(), 404);

        $month = $this->resolveMonth($request);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $slots = VisitSlot::query()
            ->where('museum_id', $museum->id)
            ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $closedDates = MuseumClosedDate::query()
            ->where('museum_id', $museum->id)
            ->whereBetween('closed_on', [$start->toDateString(), $end->toDateString()])
            ->pluck('closed_on')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $closedWeekdays = MuseumOperatingHour::query()
            ->where('museum_id', $museum->id)
            ->where('is_closed', true)
            ->pluck('day_of_week')
            ->map(fn ($day): int => (int) $day)
            ->all();

        $byDate = $slots->groupBy(fn (VisitSlot $slot): string => $slot->visit_date->toDateString());

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $dateString = $cursor->toDateString();
            $daySlots = $byDate->get($dateString, collect());

            $isClosed = in_array($dateString, $closedDates, true)
                || in_array((int) $cursor->dayOfWeek, $closedWeekdays, true);

            if ($isClosed) {
                $days[] = [
                    'date' => $dateString,
                    'status' => AvailabilityStatus::CLOSED->value,
                    'status_label' => AvailabilityStatus::CLOSED->label(),
                    'remaining' => 0,
                ];

                $cursor->addDay();

                continue;
            }

            if ($daySlots->isEmpty()) {
                // Belum digenerate: tampilkan sebagai belum tersedia, bukan penuh.
                $days[] = [
                    'date' => $dateString,
                    'status' => AvailabilityStatus::NOT_OPEN->value,
                    'status_label' => AvailabilityStatus::NOT_OPEN->label(),
                    'remaining' => 0,
                ];

                $cursor->addDay();

                continue;
            }

            $remaining = $daySlots->sum(fn (VisitSlot $slot): int => $slot->remaining());
            $capacity = $daySlots->sum(fn (VisitSlot $slot): int => $slot->capacity);

            // Status harian = yang paling longgar di antara slotnya, kecuali semua penuh.
            $status = $this->aggregateStatus($daySlots);

            $days[] = [
                'date' => $dateString,
                'status' => $status->value,
                'status_label' => $status->label(),
                'remaining' => $remaining,
                'capacity' => $capacity,
            ];

            $cursor->addDay();
        }

        return response()->json([
            'museum' => ['slug' => $museum->slug, 'name' => $museum->name],
            'month' => $month->format('Y-m'),
            'booking_mode' => $museum->booking_mode->value,
            'days' => $days,
        ]);
    }

    /**
     * Daftar slot pada satu tanggal beserta sisa kuota.
     */
    public function slots(Request $request, Museum $museum): JsonResponse
    {
        abort_unless($museum->status->isPubliclyVisible(), 404);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = (string) $validated['date'];

        $slots = VisitSlot::query()
            ->where('museum_id', $museum->id)
            ->where('visit_date', $date)
            ->orderBy('start_time')
            ->get()
            ->map(fn (VisitSlot $slot): array => [
                'id' => $slot->id,
                'label' => $slot->label(),
                'start_time' => $slot->start_time === null ? null : substr((string) $slot->start_time, 0, 5),
                'end_time' => $slot->end_time === null ? null : substr((string) $slot->end_time, 0, 5),
                'remaining' => $slot->remaining(),
                'status' => $slot->availability()->value,
                'status_label' => $slot->availability()->label(),
                'bookable' => $slot->status->isBookable() && $slot->remaining() > 0,
            ])
            ->values();

        $ticketTypes = TicketType::query()
            ->where('museum_id', $museum->id)
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TicketType $ticketType): array => [
                'id' => $ticketType->id,
                'name' => $ticketType->name,
                'price' => $ticketType->price,
                'price_label' => $ticketType->formattedPrice(),
                'description' => $ticketType->description,
            ])
            ->values();

        return response()->json([
            'museum' => ['slug' => $museum->slug, 'name' => $museum->name],
            'date' => $date,
            'booking_mode' => $museum->booking_mode->value,
            'max_tickets_per_booking' => $museum->max_tickets_per_booking,
            'slots' => $slots,
            'ticket_types' => $ticketTypes,
        ]);
    }

    /**
     * @param  Collection<int, VisitSlot>  $slots
     */
    private function aggregateStatus($slots): AvailabilityStatus
    {
        $statuses = $slots->map(fn (VisitSlot $slot): AvailabilityStatus => $slot->availability());

        if ($statuses->contains(AvailabilityStatus::AVAILABLE)) {
            return AvailabilityStatus::AVAILABLE;
        }

        if ($statuses->contains(AvailabilityStatus::ALMOST_FULL)) {
            return AvailabilityStatus::ALMOST_FULL;
        }

        if ($statuses->contains(AvailabilityStatus::FULL)) {
            return AvailabilityStatus::FULL;
        }

        return AvailabilityStatus::CLOSED;
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
