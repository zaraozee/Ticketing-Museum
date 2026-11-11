<?php

namespace App\Services\Museum;

use App\Enums\BookingMode;
use App\Enums\MuseumStatus;
use App\Models\Museum;
use App\Models\MuseumClosedDate;
use App\Models\MuseumOperatingHour;
use App\Models\OrganizerProfile;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pengelolaan konten museum oleh Organizer (FR-ORG-02..05).
 *
 * Catatan: perubahan data kritis (nama, alamat, koordinat) dicatat ke activity_logs
 * sesuai BR-21 dan acceptance criteria Fase 3 butir 6.
 */
class MuseumService
{
    /**
     * Field yang perubahannya wajib diaudit (BR-21).
     *
     * @var array<int, string>
     */
    private const CRITICAL_FIELDS = ['name', 'address', 'city', 'latitude', 'longitude'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(OrganizerProfile $profile, array $data): Museum
    {
        return DB::transaction(function () use ($profile, $data): Museum {
            $museum = new Museum($this->onlyMuseumFields($data));
            $museum->organizer_profile_id = $profile->id;
            $museum->slug = $this->uniqueSlug($data['name']);
            $museum->status = MuseumStatus::DRAFT;
            $museum->booking_mode = $museum->booking_mode ?? BookingMode::DAILY;
            $museum->save();

            $this->syncFacilities($museum, Arr::get($data, 'facility_ids', []));
            $this->syncOperatingHours($museum, Arr::get($data, 'operating_hours', []));

            ActivityLogger::log('museum.created', $museum, [
                'name' => $museum->name,
                'status' => $museum->status->value,
            ]);

            return $museum;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Museum $museum, array $data): Museum
    {
        return DB::transaction(function () use ($museum, $data): Museum {
            $before = $museum->only(self::CRITICAL_FIELDS);

            $museum->fill($this->onlyMuseumFields($data));

            if (isset($data['name']) && $data['name'] !== $museum->getOriginal('name')) {
                $museum->slug = $this->uniqueSlug($data['name'], $museum->id);
            }

            $museum->save();

            if (array_key_exists('facility_ids', $data)) {
                $this->syncFacilities($museum, (array) $data['facility_ids']);
            }

            if (array_key_exists('operating_hours', $data)) {
                $this->syncOperatingHours($museum, (array) $data['operating_hours']);
            }

            $changes = $this->criticalDiff($before, $museum->only(self::CRITICAL_FIELDS));

            if ($changes !== []) {
                ActivityLogger::log('museum.critical_updated', $museum, $changes);
            }

            return $museum->refresh();
        });
    }

    /**
     * Hapus museum (soft delete).
     *
     * Berkas gambar sengaja TIDAK dihapus di sini: baris `museums` masih ada
     * (SoftDeletes) dan galeri harus tetap utuh bila museum dipulihkan.
     * Pembersihan berkas dilakukan saat purging permanen (Fase lanjutan).
     */
    public function delete(Museum $museum): void
    {
        DB::transaction(function () use ($museum): void {
            ActivityLogger::log('museum.deleted', $museum, [
                'name' => $museum->name,
                'status' => $museum->status->value,
            ]);

            $museum->delete();
        });
    }

    /**
     * Tambah/ubah jam operasional (UQ museum_id + day_of_week).
     *
     * @param  array<int, array{day_of_week: int, open_time?: string|null, close_time?: string|null, is_closed?: bool}>  $hours
     */
    public function syncOperatingHours(Museum $museum, array $hours): void
    {
        foreach ($hours as $row) {
            MuseumOperatingHour::updateOrCreate(
                [
                    'museum_id' => $museum->id,
                    'day_of_week' => (int) $row['day_of_week'],
                ],
                [
                    'open_time' => $row['is_closed'] ?? false ? null : ($row['open_time'] ?? null),
                    'close_time' => $row['is_closed'] ?? false ? null : ($row['close_time'] ?? null),
                    'is_closed' => (bool) ($row['is_closed'] ?? false),
                ],
            );
        }
    }

    public function addClosedDate(Museum $museum, string $closedOn, ?string $reason): MuseumClosedDate
    {
        $closedDate = MuseumClosedDate::create([
            'museum_id' => $museum->id,
            'closed_on' => $closedOn,
            'reason' => $reason,
        ]);

        ActivityLogger::log('museum.closed_date_added', $museum, [
            'closed_on' => $closedOn,
            'reason' => $reason,
        ]);

        return $closedDate;
    }

    public function removeClosedDate(Museum $museum, MuseumClosedDate $closedDate): void
    {
        $closedOn = $closedDate->closed_on->toDateString();
        $closedDate->delete();

        ActivityLogger::log('museum.closed_date_removed', $museum, ['closed_on' => $closedOn]);
    }

    /**
     * @param  array<int, int|string>  $facilityIds
     */
    public function syncFacilities(Museum $museum, array $facilityIds): void
    {
        $museum->facilities()->sync(array_map('intval', $facilityIds));
    }

    /**
     * @return array<string, mixed>
     */
    private function onlyMuseumFields(array $data): array
    {
        return Arr::only($data, [
            'category_id',
            'name',
            'description',
            'address',
            'city',
            'province',
            'latitude',
            'longitude',
            'timezone',
            'booking_mode',
            'max_tickets_per_booking',
            'entry_early_minutes',
            'entry_late_minutes',
            'youtube_video_id',
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function criticalDiff(array $before, array $after): array
    {
        $diff = [];

        foreach (self::CRITICAL_FIELDS as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ((string) $old !== (string) $new) {
                $diff[$field] = ['before' => $old, 'after' => $new];
            }
        }

        return $diff;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'museum';
        $slug = $base;
        $counter = 1;

        while (
            Museum::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$counter);
        }

        return $slug;
    }
}
