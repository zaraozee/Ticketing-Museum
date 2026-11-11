<?php

use App\Enums\ContentPageType;
use App\Enums\UserRole;
use App\Models\ContentPage;
use App\Models\Facility;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\OrganizerProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::ADMIN->value);

    $this->organizer = User::factory()->create();
    $this->organizer->assignRole(UserRole::ORGANIZER->value);
    OrganizerProfile::factory()->approved()->create(['user_id' => $this->organizer->id]);
});

test('admin can create a museum category', function (): void {
    $this->actingAs($this->admin)
        ->post('/admin/categories', ['name' => 'Museum Bahari', 'is_active' => true])
        ->assertSessionHasNoErrors();

    expect(MuseumCategory::where('slug', 'museum-bahari')->exists())->toBeTrue();
});

test('museum category slug must be unique', function (): void {
    MuseumCategory::factory()->create(['name' => 'Museum Seni', 'slug' => 'museum-seni']);

    $this->actingAs($this->admin)
        ->post('/admin/categories', ['name' => 'Museum Seni Lain', 'slug' => 'museum-seni'])
        ->assertSessionHasErrors('slug');
});

test('a category that is still used by a museum cannot be deleted', function (): void {
    $category = MuseumCategory::factory()->create();
    Museum::factory()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)
        ->delete("/admin/categories/{$category->id}")
        ->assertSessionHasNoErrors();

    expect(MuseumCategory::find($category->id))->not->toBeNull();
});

test('admin can create and update a master facility', function (): void {
    $this->actingAs($this->admin)
        ->post('/admin/facilities', ['name' => 'Ruang Pamer Interaktif', 'icon' => 'museum'])
        ->assertSessionHasNoErrors();

    $facility = Facility::where('name', 'Ruang Pamer Interaktif')->first();
    expect($facility)->not->toBeNull();

    $this->actingAs($this->admin)
        ->put("/admin/facilities/{$facility->id}", ['name' => 'Ruang Interaktif', 'is_active' => false])
        ->assertSessionHasNoErrors();

    expect($facility->fresh()->name)->toBe('Ruang Interaktif')
        ->and($facility->fresh()->is_active)->toBeFalse();
});

test('facility names must be unique', function (): void {
    Facility::factory()->create(['name' => 'Toilet']);

    $this->actingAs($this->admin)
        ->post('/admin/facilities', ['name' => 'Toilet'])
        ->assertSessionHasErrors('name');
});

test('admin can publish an FAQ content page', function (): void {
    $this->actingAs($this->admin)
        ->post('/admin/content-pages', [
            'title' => 'Bagaimana cara membatalkan tiket?',
            'type' => ContentPageType::FAQ->value,
            'content' => 'Pembatalan dapat dilakukan dari halaman tiket saya.',
            'is_published' => true,
        ])
        ->assertSessionHasNoErrors();

    $page = ContentPage::where('slug', 'bagaimana-cara-membatalkan-tiket')->first();

    expect($page)->not->toBeNull()
        ->and($page->type)->toBe(ContentPageType::FAQ)
        ->and($page->is_published)->toBeTrue();
});

test('admin can update and delete a content page using the id shown in the admin table', function (): void {
    $page = ContentPage::create([
        'title' => 'Halaman Awal',
        'slug' => 'halaman-awal',
        'type' => ContentPageType::PAGE,
        'content' => 'Isi awal.',
        'is_published' => true,
    ]);

    // Tabel admin mengirim primary key (id), bukan slug. Route model binding
    // ContentPage memakai slug, jadi ini menguji keduanya tetap konsisten.
    $this->actingAs($this->admin)
        ->put("/admin/content-pages/{$page->id}", [
            'title' => 'Halaman Diubah',
            'type' => ContentPageType::PAGE->value,
            'content' => 'Isi baru.',
            'is_published' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($page->fresh()->title)->toBe('Halaman Diubah');

    $this->actingAs($this->admin)
        ->delete("/admin/content-pages/{$page->id}")
        ->assertSessionHasNoErrors();

    expect(ContentPage::find($page->id))->toBeNull();
});

test('organizer cannot manage master data', function (string $uri, string $method): void {
    $this->actingAs($this->organizer)
        ->call($method, $uri)
        ->assertForbidden();
})->with([
    'categories' => ['/admin/categories', 'GET'],
    'facilities' => ['/admin/facilities', 'GET'],
    'content pages' => ['/admin/content-pages', 'GET'],
]);

test('regular user cannot manage master data', function (): void {
    $user = User::factory()->create();
    $user->assignRole(UserRole::USER->value);

    $this->actingAs($user)->get('/admin/categories')->assertForbidden();
});

test('unauthenticated guests are redirected to login from admin master data', function (): void {
    $this->get('/admin/facilities')->assertRedirect('/login');
});
