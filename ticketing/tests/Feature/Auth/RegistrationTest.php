<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register and are assigned the user role', function () {
    $response = $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'phone' => '081234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'budi@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(UserRole::USER->value))->toBeTrue();
    expect($user->uuid)->not->toBeEmpty();

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $user->id,
        'action' => 'user.register',
    ]);

    $response->assertRedirect(route('verification.notice', absolute: false));
});
