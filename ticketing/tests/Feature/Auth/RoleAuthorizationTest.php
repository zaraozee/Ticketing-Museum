<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('unauthenticated users are redirected to login when accessing admin or organizer area', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->get('/organizer')->assertRedirect('/login');
});

test('regular users cannot access admin or organizer areas', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::USER->value);

    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get('/organizer')->assertForbidden();
});

test('organizers cannot access admin area', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(UserRole::ORGANIZER->value);

    $this->actingAs($organizer)->get('/admin')->assertForbidden();
    $this->actingAs($organizer)->get('/organizer')->assertOk();
});

test('admin can access admin area', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN->value);

    $this->actingAs($admin)->get('/admin')->assertOk();
});
