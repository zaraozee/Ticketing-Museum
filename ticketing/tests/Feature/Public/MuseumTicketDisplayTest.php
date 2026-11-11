<?php

use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\OrganizerProfile;
use App\Models\TicketType;

beforeEach(function (): void {
    $category = MuseumCategory::factory()->create();
    $profile = OrganizerProfile::factory()->approved()->create();

    $this->museum = Museum::factory()
        ->forOrganizer($profile)
        ->published()
        ->create(['category_id' => $category->id]);
});

test('the museum detail page exposes active ticket types and the lowest price', function (): void {
    TicketType::factory()->priced(25000)->create([
        'museum_id' => $this->museum->id,
        'name' => 'Dewasa',
        'sort_order' => 1,
    ]);

    TicketType::factory()->priced(10000)->create([
        'museum_id' => $this->museum->id,
        'name' => 'Pelajar',
        'sort_order' => 2,
    ]);

    // Nonaktif tidak boleh ikut dihitung sebagai harga terendah.
    TicketType::factory()->priced(1000)->inactive()->create([
        'museum_id' => $this->museum->id,
        'name' => 'Promo Lama',
    ]);

    $page = $this->get("/museums/{$this->museum->slug}")->assertOk();

    $props = $page->viewData('page')['props'];

    expect($props['lowestPrice'])->toBe(10000)
        ->and($props['ticketTypes'])->toHaveCount(2)
        ->and($props['ticketTypes'][0]['name'])->toBe('Dewasa')
        ->and($props['ticketTypes'][0]['price_label'])->toBe('Rp 25.000')
        ->and($props['ticketTypes'][1]['name'])->toBe('Pelajar');
});

test('a museum without ticket types reports no starting price', function (): void {
    $page = $this->get("/museums/{$this->museum->slug}")->assertOk();

    $props = $page->viewData('page')['props'];

    expect($props['lowestPrice'])->toBeNull()
        ->and($props['ticketTypes'])->toBe([]);
});
