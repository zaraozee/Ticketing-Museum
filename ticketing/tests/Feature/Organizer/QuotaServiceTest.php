<?php

use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\OrganizerProfile;
use App\Models\VisitSlot;
use App\Services\Quota\QuotaService;

beforeEach(function (): void {
    $category = MuseumCategory::factory()->create();
    $profile = OrganizerProfile::factory()->approved()->create();

    $this->museum = Museum::factory()
        ->forOrganizer($profile)
        ->published()
        ->create(['category_id' => $category->id]);

    $this->quota = app(QuotaService::class);
});

function slotFor(Museum $museum, int $capacity = 10): VisitSlot
{
    return VisitSlot::factory()->create([
        'museum_id' => $museum->id,
        'capacity' => $capacity,
        'reserved_count' => 0,
        'sold_count' => 0,
    ]);
}

test('reserving quota increases reserved_count', function (): void {
    $slot = slotFor($this->museum, 10);

    $ok = $this->quota->reserve($slot, 3);

    expect($ok)->toBeTrue()
        ->and($slot->fresh()->reserved_count)->toBe(3)
        ->and($slot->fresh()->remaining())->toBe(7);
});

test('reserving more than the remaining quota fails and changes nothing', function (): void {
    $slot = slotFor($this->museum, 5);

    expect($this->quota->reserve($slot, 6))->toBeFalse()
        ->and($slot->fresh()->reserved_count)->toBe(0);
});

test('quota cannot be reserved twice beyond capacity', function (): void {
    $slot = slotFor($this->museum, 5);

    expect($this->quota->reserve($slot, 3))->toBeTrue()
        ->and($this->quota->reserve($slot, 3))->toBeFalse()
        ->and($slot->fresh()->reserved_count)->toBe(3);
});

test('releasing quota returns the reserved amount', function (): void {
    $slot = slotFor($this->museum, 10);

    $this->quota->reserve($slot, 4);
    $this->quota->release($slot, 4);

    expect($slot->fresh()->reserved_count)->toBe(0)
        ->and($slot->fresh()->remaining())->toBe(10);
});

test('releasing more than reserved never makes reserved_count negative', function (): void {
    $slot = slotFor($this->museum, 10);

    $this->quota->reserve($slot, 2);
    $this->quota->release($slot, 5);

    expect($slot->fresh()->reserved_count)->toBe(0);
});

test('confirming moves quota from reserved to sold', function (): void {
    $slot = slotFor($this->museum, 10);

    $this->quota->reserve($slot, 4);
    $this->quota->confirm($slot, 4);

    $fresh = $slot->fresh();

    expect($fresh->reserved_count)->toBe(0)
        ->and($fresh->sold_count)->toBe(4)
        ->and($fresh->remaining())->toBe(6);
});

test('capacity cannot be lowered below reserved plus sold (BR-20)', function (): void {
    $slot = slotFor($this->museum, 20);
    $slot->update(['reserved_count' => 3, 'sold_count' => 5]);

    // 7 < 8 (terpakai) -> harus ditolak.
    expect($this->quota->changeCapacity($slot, 7))->toBeFalse()
        ->and($slot->fresh()->capacity)->toBe(20);

    // 8 == 8 -> boleh (batas bawah tepat).
    expect($this->quota->changeCapacity($slot, 8))->toBeTrue()
        ->and($slot->fresh()->capacity)->toBe(8);
});

test('a closed slot cannot be reserved', function (): void {
    $slot = VisitSlot::factory()->closed()->create([
        'museum_id' => $this->museum->id,
        'capacity' => 10,
    ]);

    expect($this->quota->reserve($slot, 1))->toBeFalse()
        ->and($slot->fresh()->reserved_count)->toBe(0);
});

test('reserving a quantity below one is rejected', function (): void {
    $slot = slotFor($this->museum, 10);

    expect(fn () => $this->quota->reserve($slot, 0))
        ->toThrow(InvalidArgumentException::class);
});

test('sequential competition for the last seats lets only one caller win', function (): void {
    $slot = slotFor($this->museum, 1);

    // Meniru dua permintaan yang datang berurutan: yang kedua harus gagal
    // karena kuota sudah habis ditahan permintaan pertama.
    $first = $this->quota->reserve($slot, 1);
    $second = $this->quota->reserve($slot, 1);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($slot->fresh()->reserved_count)->toBe(1)
        ->and($slot->fresh()->reserved_count)->toBeLessThanOrEqual($slot->fresh()->capacity);
});

test('availability status reflects remaining quota', function (): void {
    $slot = slotFor($this->museum, 100);

    expect($slot->availability()->value)->toBe('available');

    // Sisa 15% -> hampir penuh.
    $slot->update(['sold_count' => 85]);
    expect($slot->fresh()->availability()->value)->toBe('almost_full');

    // Habis -> penuh.
    $slot->update(['sold_count' => 100]);
    expect($slot->fresh()->availability()->value)->toBe('full');
});

test('a closed slot always reports closed availability', function (): void {
    $slot = VisitSlot::factory()->closed()->create([
        'museum_id' => $this->museum->id,
        'capacity' => 100,
        'sold_count' => 0,
    ]);

    expect($slot->availability()->value)->toBe('closed');
});
