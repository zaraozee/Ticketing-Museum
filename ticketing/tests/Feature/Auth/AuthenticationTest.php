<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::USER->value);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $user->id,
        'action' => 'user.login',
    ]);
});

test('admin is redirected to admin dashboard after login', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN->value);

    $response = $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

test('organizer is redirected to organizer dashboard after login', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(UserRole::ORGANIZER->value);

    $response = $this->post('/login', [
        'email' => $organizer->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('organizer.dashboard', absolute: false));
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $user->id,
        'action' => 'user.logout',
    ]);
});
