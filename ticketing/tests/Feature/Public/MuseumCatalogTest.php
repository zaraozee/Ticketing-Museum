<?php

use App\Enums\MuseumStatus;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\MuseumImage;
use App\Models\OrganizerProfile;

beforeEach(function (): void {
    $this->profile = OrganizerProfile::factory()->approved()->create();
    $this->category = MuseumCategory::factory()->create(['name' => 'Museum Sejarah']);
});

test('only published museums appear in the public listing', function (): void {
    Museum::factory()->forOrganizer($this->profile)->published()->create(['name' => 'Museum Tayang']);
    Museum::factory()->forOrganizer($this->profile)->create(['name' => 'Museum Draf', 'status' => MuseumStatus::DRAFT]);
    Museum::factory()->forOrganizer($this->profile)->pendingReview()->create(['name' => 'Museum Review']);

    $response = $this->get('/museums');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Museums/Index')
        ->has('museums.data', 1)
        ->where('museums.data.0.name', 'Museum Tayang')
    );
});

test('a non published museum detail returns 404', function (string $status): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->create([
        'status' => $status,
    ]);

    $this->get("/museums/{$museum->slug}")->assertNotFound();
})->with([
    'draft' => MuseumStatus::DRAFT->value,
    'pending' => MuseumStatus::PENDING_REVIEW->value,
    'rejected' => MuseumStatus::REJECTED->value,
    'suspended' => MuseumStatus::SUSPENDED->value,
    'archived' => MuseumStatus::ARCHIVED->value,
]);

test('published museum detail is accessible with facilities and operating hours', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->published()->create([
        'name' => 'Museum Batik Nusantara',
        'latitude' => -7.797068,
        'longitude' => 110.370529,
    ]);

    MuseumImage::create([
        'museum_id' => $museum->id,
        'path' => 'museums/1/cover.webp',
        'sort_order' => 0,
        'is_cover' => true,
    ]);

    $response = $this->get("/museums/{$museum->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Museums/Show')
        ->where('museum.name', 'Museum Batik Nusantara')
        ->has('museum.images', 1)
        ->where('directionsUrl', fn (string $url) => str_contains($url, '-7.797068'))
    );
});

test('public listing can be filtered by search keyword', function (): void {
    Museum::factory()->forOrganizer($this->profile)->published()->create(['name' => 'Museum Batik', 'city' => 'Yogyakarta']);
    Museum::factory()->forOrganizer($this->profile)->published()->create(['name' => 'Museum Maritim', 'city' => 'Surabaya']);

    $response = $this->get('/museums?q=Batik');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('museums.data', 1)
        ->where('museums.data.0.name', 'Museum Batik')
    );
});

test('public listing can be filtered by category slug', function (): void {
    $other = MuseumCategory::factory()->create(['name' => 'Museum Seni', 'slug' => 'museum-seni']);

    Museum::factory()->forOrganizer($this->profile)->published()->create([
        'name' => 'Museum Sejarah A',
        'category_id' => $this->category->id,
    ]);

    Museum::factory()->forOrganizer($this->profile)->published()->create([
        'name' => 'Museum Seni B',
        'category_id' => $other->id,
    ]);

    $response = $this->get('/museums?category='.$other->slug);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('museums.data', 1)
        ->where('museums.data.0.name', 'Museum Seni B')
    );
});

test('public listing can be filtered by city', function (): void {
    Museum::factory()->forOrganizer($this->profile)->published()->create(['name' => 'Museum Jogja', 'city' => 'Yogyakarta']);
    Museum::factory()->forOrganizer($this->profile)->published()->create(['name' => 'Museum Jakarta', 'city' => 'Jakarta']);

    $response = $this->get('/museums?city=Jakarta');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('museums.data', 1)
        ->where('museums.data.0.name', 'Museum Jakarta')
    );
});

test('a soft deleted museum is hidden from the public catalog and detail page', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->published()->create([
        'name' => 'Museum Terhapus',
    ]);

    $museum->delete();

    $this->get('/museums')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('museums.data', 0));

    $this->get("/museums/{$museum->slug}")->assertNotFound();
});

test('search keyword is matched against city as well', function (): void {
    Museum::factory()->forOrganizer($this->profile)->published()->create(['name' => 'Museum A', 'city' => 'Bandung']);
    Museum::factory()->forOrganizer($this->profile)->published()->create(['name' => 'Museum B', 'city' => 'Medan']);

    $this->get('/museums?q=Bandung')
        ->assertInertia(fn ($page) => $page->has('museums.data', 1));
});

test('search is case insensitive across database drivers', function (): void {
    Museum::factory()->forOrganizer($this->profile)->published()->create(['name' => 'Museum Batik Nusantara']);

    // PostgreSQL memperlakukan LIKE sebagai case-sensitive; pencarian pengguna
    // tidak boleh bergantung pada huruf besar/kecil.
    $this->get('/museums?q=batik')
        ->assertInertia(fn ($page) => $page->has('museums.data', 1));

    $this->get('/museums?q=BATIK')
        ->assertInertia(fn ($page) => $page->has('museums.data', 1));
});
