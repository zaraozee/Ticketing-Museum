<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create([
        'name' => 'Nama Lama',
        'phone' => '0811111111',
    ]);

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => 'Nama Baru',
        'email' => $user->email,
        'phone' => '0822222222',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $user->refresh();
    expect($user->name)->toBe('Nama Baru');
    expect($user->phone)->toBe('0822222222');

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $user->id,
        'action' => 'user.profile_update',
    ]);
});

test('email change resets email verification and logs activity', function () {
    $user = User::factory()->create([
        'email' => 'lama@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => 'baru@example.com',
        'phone' => $user->phone,
    ]);

    $response->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->email)->toBe('baru@example.com');
    expect($user->email_verified_at)->toBeNull();

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $user->id,
        'action' => 'user.profile_update',
    ]);
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->actingAs($user)->put('/profile/password', [
        'current_password' => 'old-password',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertSessionHasNoErrors();

    $this->assertTrue(Hash::check('new-password123', $user->refresh()->password));

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $user->id,
        'action' => 'user.password_change',
    ]);
});
