<?php

use App\Enums\OrganizerStatus;
use App\Enums\UserRole;
use App\Models\OrganizerProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('organizer registration screen can be rendered', function () {
    $response = $this->get('/register/organizer');

    $response->assertStatus(200);
});

test('organizers can register with pending status and organizer role', function () {
    $response = $this->post('/register/organizer', [
        'name' => 'Dewi Sartika',
        'email' => 'dewi@museum-tekstil.test',
        'phone' => '081299998888',
        'organization_name' => 'Yayasan Museum Tekstil Nusantara',
        'contact_phone' => '021-5551234',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'dewi@museum-tekstil.test')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(UserRole::ORGANIZER->value))->toBeTrue();

    $profile = OrganizerProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull();
    expect($profile->organization_name)->toBe('Yayasan Museum Tekstil Nusantara');
    expect($profile->status)->toBe(OrganizerStatus::PENDING);

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $user->id,
        'action' => 'organizer.register',
    ]);

    $response->assertRedirect(route('organizer.dashboard', absolute: false));
});
