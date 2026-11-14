<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * Akun hasil seeder harus terverifikasi.
 *
 * `email_verified_at` tidak mass-assignable (batas keamanan yang disengaja).
 * Seeder yang mengisinya lewat array atribut akan dibuang diam-diam, dan
 * akun demo lalu terblokir middleware `verified` pada halaman booking.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the seeded admin account is verified', function (): void {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'admin@musentix.test')->firstOrFail();

    expect($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull();
});

test('the seeded demo accounts are verified', function (): void {
    $this->seed(DemoDataSeeder::class);

    $visitor = User::where('email', 'user@test.com')->firstOrFail();
    $organizer = User::where('email', 'organizer@test.com')->firstOrFail();

    expect($visitor->hasVerifiedEmail())->toBeTrue()
        ->and($organizer->hasVerifiedEmail())->toBeTrue();
});

test('seeding twice does not disturb verification', function (): void {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::where('email', 'admin@musentix.test')->firstOrFail()->hasVerifiedEmail())->toBeTrue();
});

test('a seeded demo visitor can reach the booking page', function (): void {
    // Bukti bahwa seeder dan penegakan verifikasi tidak saling bertabrakan.
    $this->seed(DemoDataSeeder::class);

    $visitor = User::where('email', 'user@test.com')->firstOrFail();

    $this->actingAs($visitor)->get(route('bookings.index'))->assertOk();
});
