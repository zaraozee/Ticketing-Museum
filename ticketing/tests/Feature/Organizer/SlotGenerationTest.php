<?php

use App\Enums\MuseumStatus;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\MuseumClosedDate;
use App\Models\MuseumOperatingHour;
use App\Models\OrganizerProfile;
use App\Models\SlotCapacityOverride;
use App\Models\SlotTemplate;
use App\Models\VisitSlot;
use App\Services\Slot\SlotGenerator;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->category = MuseumCategory::factory()->create();

    $profile = OrganizerProfile::factory()->approved()->create();

    $this->museum = Museum::factory()
        ->forOrganizer($profile)
        ->published()
        ->create([
            'category_id' => $this->category->id,
            'booking_mode' => 'timed_slot',
        ]);

    // Buka setiap hari agar fokus pengujian pada generator, bukan jadwal.
    foreach (range(0, 6) as $day) {
        MuseumOperatingHour::create([
            'museum_id' => $this->museum->id,
            'day_of_week' => $day,
            'open_time' => '09:00',
            'close_time' => '17:00',
            'is_closed' => false,
        ]);
    }

    $this->generator = app(SlotGenerator::class);
});

test('generating slots twice does not duplicate rows (idempotent)', function (): void {
    SlotTemplate::factory()->create([
        'museum_id' => $this->museum->id,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'capacity' => 40,
    ]);

    $from = Carbon::parse('2026-12-01');
    $to = Carbon::parse('2026-12-03');

    $first = $this->generator->generate($this->museum, $from->copy(), $to->copy());
    expect($first['created'])->toBe(3)
        ->and(VisitSlot::where('museum_id', $this->museum->id)->count())->toBe(3);

    // Penjalanan kedua: tidak boleh membuat baris baru.
    $second = $this->generator->generate($this->museum, $from->copy(), $to->copy());

    expect($second['created'])->toBe(0)
        ->and(VisitSlot::where('museum_id', $this->museum->id)->count())->toBe(3);
});

test('the command is idempotent when run twice', function (): void {
    SlotTemplate::factory()->create([
        'museum_id' => $this->museum->id,
        'capacity' => 25,
    ]);

    $this->artisan('slots:generate', [
        '--museum' => $this->museum->slug,
        '--from' => '2026-12-01',
        '--to' => '2026-12-02',
    ])->assertSuccessful();

    $afterFirst = VisitSlot::where('museum_id', $this->museum->id)->count();

    $this->artisan('slots:generate', [
        '--museum' => $this->museum->slug,
        '--from' => '2026-12-01',
        '--to' => '2026-12-02',
    ])->assertSuccessful();

    expect($afterFirst)->toBe(2)
        ->and(VisitSlot::where('museum_id', $this->museum->id)->count())->toBe(2);
});

test('closed dates produce no slots', function (): void {
    SlotTemplate::factory()->create([
        'museum_id' => $this->museum->id,
        'capacity' => 30,
    ]);

    MuseumClosedDate::create([
        'museum_id' => $this->museum->id,
        'closed_on' => '2026-12-02',
        'reason' => 'Libur nasional',
    ]);

    $this->generator->generate(
        $this->museum,
        Carbon::parse('2026-12-01'),
        Carbon::parse('2026-12-03'),
    );

    $dates = VisitSlot::where('museum_id', $this->museum->id)
        ->orderBy('visit_date')
        ->pluck('visit_date')
        ->map(fn ($date): string => Carbon::parse($date)->toDateString())
        ->all();

    expect($dates)->toBe(['2026-12-01', '2026-12-03']);
});

test('weekly closed days produce no slots', function (): void {
    SlotTemplate::factory()->create([
        'museum_id' => $this->museum->id,
        'capacity' => 30,
    ]);

    // Tutup setiap Senin.
    MuseumOperatingHour::where('museum_id', $this->museum->id)
        ->where('day_of_week', 1)
        ->update(['is_closed' => true]);

    // 2026-12-07 adalah hari Senin.
    $this->generator->generate(
        $this->museum,
        Carbon::parse('2026-12-06'),
        Carbon::parse('2026-12-08'),
    );

    $dates = VisitSlot::where('museum_id', $this->museum->id)
        ->orderBy('visit_date')
        ->pluck('visit_date')
        ->map(fn ($date): string => Carbon::parse($date)->toDateString())
        ->all();

    expect($dates)->toBe(['2026-12-06', '2026-12-08']);
});

test('a capacity override for a date is used instead of the template capacity', function (): void {
    SlotTemplate::factory()->create([
        'museum_id' => $this->museum->id,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'capacity' => 50,
    ]);

    SlotCapacityOverride::create([
        'museum_id' => $this->museum->id,
        'visit_date' => '2026-12-02',
        'start_time' => '09:00:00',
        'capacity' => 5,
        'note' => 'Perbaikan ruang pamer',
    ]);

    $this->generator->generate(
        $this->museum,
        Carbon::parse('2026-12-01'),
        Carbon::parse('2026-12-03'),
    );

    $capacities = VisitSlot::where('museum_id', $this->museum->id)
        ->orderBy('visit_date')
        ->pluck('capacity', 'visit_date')
        ->map(fn ($capacity): int => (int) $capacity)
        ->all();

    expect($capacities['2026-12-01'])->toBe(50)
        ->and($capacities['2026-12-02'])->toBe(5)
        ->and($capacities['2026-12-03'])->toBe(50);
});

test('regenerating does not lower capacity below reserved plus sold (BR-20)', function (): void {
    $template = SlotTemplate::factory()->create([
        'museum_id' => $this->museum->id,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'capacity' => 50,
    ]);

    $this->generator->generate(
        $this->museum,
        Carbon::parse('2026-12-01'),
        Carbon::parse('2026-12-01'),
    );

    $slot = VisitSlot::where('museum_id', $this->museum->id)->firstOrFail();
    $slot->update(['reserved_count' => 8, 'sold_count' => 10]);

    // Template diturunkan ke 5, di bawah 18 yang sudah terpakai.
    $template->update(['capacity' => 5]);

    $result = $this->generator->generate(
        $this->museum,
        Carbon::parse('2026-12-01'),
        Carbon::parse('2026-12-01'),
    );

    expect($slot->fresh()->capacity)->toBe(50)
        ->and($result['skipped'])->toBe(1);
});

test('inactive templates produce no slots', function (): void {
    SlotTemplate::factory()->inactive()->create([
        'museum_id' => $this->museum->id,
    ]);

    $this->generator->generate(
        $this->museum,
        Carbon::parse('2026-12-01'),
        Carbon::parse('2026-12-03'),
    );

    expect(VisitSlot::where('museum_id', $this->museum->id)->count())->toBe(0);
});

test('only published museums are processed by the command', function (): void {
    $profile = OrganizerProfile::factory()->approved()->create();

    $draft = Museum::factory()->forOrganizer($profile)->create([
        'category_id' => $this->category->id,
        'status' => MuseumStatus::DRAFT,
    ]);

    SlotTemplate::factory()->create(['museum_id' => $draft->id]);
    SlotTemplate::factory()->create(['museum_id' => $this->museum->id]);

    $this->artisan('slots:generate', [
        '--from' => '2026-12-01',
        '--to' => '2026-12-01',
    ])->assertSuccessful();

    expect(VisitSlot::where('museum_id', $draft->id)->count())->toBe(0)
        ->and(VisitSlot::where('museum_id', $this->museum->id)->count())->toBe(1);
});
