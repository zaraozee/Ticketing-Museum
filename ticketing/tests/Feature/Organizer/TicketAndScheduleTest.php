<?php

use App\Enums\UserRole;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\MuseumOperatingHour;
use App\Models\OrganizerProfile;
use App\Models\SlotCapacityOverride;
use App\Models\SlotTemplate;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->organizer = User::factory()->create();
    $this->organizer->assignRole(UserRole::ORGANIZER->value);

    $profile = OrganizerProfile::factory()->approved()->create([
        'user_id' => $this->organizer->id,
    ]);

    $this->museum = Museum::factory()
        ->forOrganizer($profile)
        ->published()
        ->create([
            'category_id' => MuseumCategory::factory()->create()->id,
            'booking_mode' => 'timed_slot',
        ]);
});

test('organizer can create a ticket type', function (): void {
    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/ticket-types", [
            'name' => 'Dewasa',
            'price' => 25000,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $ticketType = TicketType::where('museum_id', $this->museum->id)->first();

    expect($ticketType)->not->toBeNull()
        ->and($ticketType->name)->toBe('Dewasa')
        ->and($ticketType->price)->toBe(25000);
});

test('ticket type name must be unique within the museum', function (): void {
    TicketType::factory()->create([
        'museum_id' => $this->museum->id,
        'name' => 'Dewasa',
    ]);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/ticket-types", [
            'name' => 'Dewasa',
            'price' => 30000,
        ])
        ->assertSessionHasErrors('name');
});

test('the same ticket type name is allowed in a different museum', function (): void {
    $otherMuseum = Museum::factory()->published()->create([
        'category_id' => $this->museum->category_id,
    ]);

    TicketType::factory()->create([
        'museum_id' => $otherMuseum->id,
        'name' => 'Dewasa',
    ]);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/ticket-types", [
            'name' => 'Dewasa',
            'price' => 20000,
        ])
        ->assertSessionHasNoErrors();

    expect(TicketType::where('museum_id', $this->museum->id)->count())->toBe(1);
});

test('a negative price is rejected', function (): void {
    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/ticket-types", [
            'name' => 'Gratis',
            'price' => -1,
        ])
        ->assertSessionHasErrors('price');
});

test('an organizer cannot manage ticket types of another museum', function (): void {
    $other = User::factory()->create();
    $other->assignRole(UserRole::ORGANIZER->value);
    OrganizerProfile::factory()->approved()->create(['user_id' => $other->id]);

    $this->actingAs($other)
        ->post("/organizer/museums/{$this->museum->slug}/ticket-types", [
            'name' => 'Dewasa',
            'price' => 25000,
        ])
        ->assertForbidden();
});

test('organizer can create a slot template', function (): void {
    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/slot-templates", [
            'name' => 'Sesi Pagi',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'capacity' => 50,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $template = SlotTemplate::where('museum_id', $this->museum->id)->first();

    expect($template)->not->toBeNull()
        ->and($template->capacity)->toBe(50);
});

test('a daily museum rejects slot templates with times', function (): void {
    $this->museum->update(['booking_mode' => 'daily']);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/slot-templates", [
            'name' => 'Sepanjang Hari',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'capacity' => 100,
        ])
        ->assertSessionHasErrors('start_time');
});

test('end time must be after start time', function (): void {
    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/slot-templates", [
            'name' => 'Sesi Salah',
            'start_time' => '11:00',
            'end_time' => '09:00',
            'capacity' => 50,
        ])
        ->assertSessionHasErrors('end_time');
});

test('organizer can generate slots for the displayed month', function (): void {
    SlotTemplate::factory()->create([
        'museum_id' => $this->museum->id,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'capacity' => 20,
    ]);

    foreach (range(0, 6) as $day) {
        MuseumOperatingHour::create([
            'museum_id' => $this->museum->id,
            'day_of_week' => $day,
            'open_time' => '09:00',
            'close_time' => '17:00',
            'is_closed' => false,
        ]);
    }

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/schedule/generate?month=2026-12")
        ->assertSessionHasNoErrors();

    expect(VisitSlot::where('museum_id', $this->museum->id)->count())->toBe(31);
});

test('capacity cannot be lowered below reserved plus sold through the UI (BR-20)', function (): void {
    $slot = VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'capacity' => 20,
        'reserved_count' => 4,
        'sold_count' => 6,
    ]);

    $this->actingAs($this->organizer)
        ->patch("/organizer/museums/{$this->museum->slug}/schedule/slots/{$slot->id}", [
            'capacity' => 9,
        ])
        ->assertSessionHasErrors('capacity');

    expect($slot->fresh()->capacity)->toBe(20);

    // Tepat di batas (10 == 4 + 6) diperbolehkan.
    $this->actingAs($this->organizer)
        ->patch("/organizer/museums/{$this->museum->slug}/schedule/slots/{$slot->id}", [
            'capacity' => 10,
        ])
        ->assertSessionHasNoErrors();

    expect($slot->fresh()->capacity)->toBe(10);
});

test('a capacity override applies to the existing slot', function (): void {
    $slot = VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => now()->addDays(5)->toDateString(),
        'start_time' => '09:00:00',
        'capacity' => 50,
    ]);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/slot-overrides", [
            'visit_date' => $slot->visit_date->toDateString(),
            'start_time' => '09:00',
            'capacity' => 8,
            'note' => 'Perbaikan ruang',
        ])
        ->assertSessionHasNoErrors();

    expect($slot->fresh()->capacity)->toBe(8)
        ->and(SlotCapacityOverride::where('museum_id', $this->museum->id)->count())->toBe(1);
});
