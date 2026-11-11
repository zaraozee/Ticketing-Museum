<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('redirects to google provider', function () {
    $response = $this->get('/auth/google');

    $response->assertRedirect();
});

test('new user can login via google and account is created', function () {
    $abstractUser = Mockery::mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-id-12345');
        $mock->shouldReceive('getName')->andReturn('Rian Pratama');
        $mock->shouldReceive('getEmail')->andReturn('rian@gmail.com');
    });

    Socialite::shouldReceive('driver->user')->andReturn($abstractUser);

    $response = $this->get('/auth/google/callback');

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    $user = User::where('email', 'rian@gmail.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Rian Pratama');
    expect($user->hasRole(UserRole::USER->value))->toBeTrue();

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-id-12345',
    ]);
});

test('existing user logging in via google links account and does not create duplicate user', function () {
    // User already exists
    $existingUser = User::factory()->create([
        'email' => 'rian@gmail.com',
        'name' => 'Rian Asli',
    ]);
    $existingUser->assignRole(UserRole::USER->value);

    $abstractUser = Mockery::mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-id-99999');
        $mock->shouldReceive('getName')->andReturn('Rian Google');
        $mock->shouldReceive('getEmail')->andReturn('rian@gmail.com');
    });

    Socialite::shouldReceive('driver->user')->andReturn($abstractUser);

    $response = $this->get('/auth/google/callback');

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    // Ensure total users with this email is exactly 1 (no duplicates)
    expect(User::where('email', 'rian@gmail.com')->count())->toBe(1);

    // Ensure social account is linked to the existing user
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $existingUser->id,
        'provider' => 'google',
        'provider_user_id' => 'google-id-99999',
    ]);
});
