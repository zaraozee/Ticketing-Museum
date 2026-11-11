<?php

use App\Enums\MuseumStatus;
use App\Enums\OrganizerStatus;
use App\Enums\UserRole;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\OrganizerProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->category = MuseumCategory::factory()->create();

    $this->organizerUser = User::factory()->create();
    $this->organizerUser->assignRole(UserRole::ORGANIZER->value);
    $this->organizerProfile = OrganizerProfile::factory()->approved()->create([
        'user_id' => $this->organizerUser->id,
    ]);

    $this->otherOrganizerUser = User::factory()->create();
    $this->otherOrganizerUser->assignRole(UserRole::ORGANIZER->value);
    $this->otherProfile = OrganizerProfile::factory()->approved()->create([
        'user_id' => $this->otherOrganizerUser->id,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::ADMIN->value);
});

test('organizer only sees their own museums in the list', function (): void {
    $own = Museum::factory()->forOrganizer($this->organizerProfile)->create(['name' => 'Museum Milik Sendiri']);
    Museum::factory()->forOrganizer($this->otherProfile)->create(['name' => 'Museum Organizer Lain']);

    $response = $this->actingAs($this->organizerUser)->get('/organizer/museums');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Organizer/Museums/Index')
        ->has('museums.data', 1)
        ->where('museums.data.0.name', $own->name)
    );
});

test('organizer cannot edit another organizer museum', function (): void {
    $foreign = Museum::factory()->forOrganizer($this->otherProfile)->create();

    $this->actingAs($this->organizerUser)
        ->get("/organizer/museums/{$foreign->slug}/edit")
        ->assertForbidden();
});

test('organizer cannot update another organizer museum', function (): void {
    $foreign = Museum::factory()->forOrganizer($this->otherProfile)->create();

    $this->actingAs($this->organizerUser)
        ->patch("/organizer/museums/{$foreign->slug}", [
            'name' => 'Diubah Paksa',
            'category_id' => $this->category->id,
        ])
        ->assertForbidden();

    expect($foreign->fresh()->name)->not->toBe('Diubah Paksa');
});

test('organizer cannot delete another organizer museum', function (): void {
    $foreign = Museum::factory()->forOrganizer($this->otherProfile)->create();

    $this->actingAs($this->organizerUser)
        ->delete("/organizer/museums/{$foreign->slug}")
        ->assertForbidden();

    expect(Museum::find($foreign->id))->not->toBeNull();
});

test('organizer cannot submit another organizer museum for review', function (): void {
    $foreign = Museum::factory()->forOrganizer($this->otherProfile)->create();

    $this->actingAs($this->organizerUser)
        ->post("/organizer/museums/{$foreign->slug}/submit")
        ->assertForbidden();
});

test('regular user cannot access organizer museum management', function (): void {
    $user = User::factory()->create();
    $user->assignRole(UserRole::USER->value);

    $this->actingAs($user)->get('/organizer/museums')->assertForbidden();
});

test('unverified organizer cannot create a museum', function (): void {
    $pendingUser = User::factory()->create();
    $pendingUser->assignRole(UserRole::ORGANIZER->value);
    OrganizerProfile::factory()->create([
        'user_id' => $pendingUser->id,
        'status' => OrganizerStatus::PENDING,
    ]);

    $this->actingAs($pendingUser)
        ->post('/organizer/museums', [
            'name' => 'Museum Baru',
            'category_id' => $this->category->id,
        ])
        ->assertForbidden();
});

test('approved organizer can create a museum', function (): void {
    $this->actingAs($this->organizerUser)
        ->post('/organizer/museums', [
            'name' => 'Museum Batik Nusantara',
            'category_id' => $this->category->id,
            'city' => 'Yogyakarta',
        ])
        ->assertRedirect();

    $museum = Museum::where('name', 'Museum Batik Nusantara')->first();

    expect($museum)->not->toBeNull()
        ->and($museum->status)->toBe(MuseumStatus::DRAFT)
        ->and($museum->organizer_profile_id)->toBe($this->organizerProfile->id);
});

test('organizer cannot access admin museum review area', function (): void {
    $this->actingAs($this->organizerUser)->get('/admin/museums')->assertForbidden();
});
