<?php

use App\Enums\MuseumStatus;
use App\Enums\OrganizerStatus;
use App\Enums\UserRole;
use App\Models\Museum;
use App\Models\MuseumStaff;
use App\Models\OrganizerProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Akses petugas gate ke halaman pemindaian
|--------------------------------------------------------------------------
|
| `SOP_GATE.md` §1 memerintahkan petugas gate membuka `/organizer/scanner`.
| Petugas gate bukan organizer: mereka memakai peran `user` dengan baris
| `museum_staff` aktif. Rute scanner sempat berada di dalam grup
| `role:organizer`, sehingga petugas gate mendapat 403 di halaman yang justru
| menjadi tugas utamanya — sementara API `/api/v1/gate/*` sudah menerima mereka.
| Halaman yang tidak dapat dibuka sama saja dengan fitur yang tidak ada.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function publishedMuseumOwnedByOrganizer(): Museum
{
    $organizer = User::factory()->create(['email_verified_at' => now()]);
    $organizer->assignRole(UserRole::ORGANIZER->value);

    $profile = OrganizerProfile::factory()->for($organizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    return Museum::factory()->create([
        'organizer_profile_id' => $profile->id,
        'status' => MuseumStatus::PUBLISHED,
    ]);
}

function gateStaffFor(Museum $museum): User
{
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole(UserRole::USER->value);

    MuseumStaff::create([
        'museum_id' => $museum->id,
        'user_id' => $staff->id,
        'staff_role' => 'gate_staff',
        'is_active' => true,
    ]);

    return $staff;
}

test('a gate staff member can open the scanner page', function (): void {
    $museum = publishedMuseumOwnedByOrganizer();
    $staff = gateStaffFor($museum);

    $response = $this->actingAs($staff)->get('/organizer/scanner');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Organizer/Gate/Scanner')
        ->has('museums', 1)
        ->where('museums.0.id', $museum->id)
    );
});

test('a gate staff member can open the scan history page', function (): void {
    $museum = publishedMuseumOwnedByOrganizer();
    $staff = gateStaffFor($museum);

    $response = $this->actingAs($staff)->get('/organizer/scans');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Organizer/Gate/History')
        ->where('selectedMuseum', $museum->id)
    );
});

test('an organizer can still open the scanner page', function (): void {
    $museum = publishedMuseumOwnedByOrganizer();
    $organizer = $museum->organizerProfile->user;

    $response = $this->actingAs($organizer)->get('/organizer/scanner');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Organizer/Gate/Scanner')
        ->has('museums', 1)
    );
});

test('a regular user without a staff row cannot open the scanner page', function (): void {
    // Membuka scanner berarti dapat mencoba memindai tiket museum mana pun.
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(UserRole::USER->value);

    $this->actingAs($user)
        ->get('/organizer/scanner')
        ->assertForbidden();
});

test('an inactive staff row does not grant scanner access', function (): void {
    // Menonaktifkan petugas harus benar-benar mencabut akses, bukan sekadar
    // menyembunyikan menu.
    $museum = publishedMuseumOwnedByOrganizer();

    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole(UserRole::USER->value);

    MuseumStaff::create([
        'museum_id' => $museum->id,
        'user_id' => $staff->id,
        'staff_role' => 'gate_staff',
        'is_active' => false,
    ]);

    $this->actingAs($staff)
        ->get('/organizer/scanner')
        ->assertForbidden();
});

test('a guest is redirected to login from the scanner page', function (): void {
    $this->get('/organizer/scanner')->assertRedirect('/login');
});

test('the scanner access is limited to the gate pages not the whole organizer area', function (): void {
    // Petugas gate hanya boleh membuka halaman pemindaian. Laporan memuat data
    // pribadi pemesan dan pengelolaan museum mengubah data publik.
    $museum = publishedMuseumOwnedByOrganizer();
    $staff = gateStaffFor($museum);

    $this->actingAs($staff)->get('/organizer')->assertForbidden();
    $this->actingAs($staff)->get('/organizer/reports')->assertForbidden();
    $this->actingAs($staff)->get('/organizer/museums')->assertForbidden();
});

test('the shared auth props tell the ui whether the user may scan', function (): void {
    $museum = publishedMuseumOwnedByOrganizer();
    $staff = gateStaffFor($museum);

    $response = $this->actingAs($staff)->get('/organizer/scanner');

    $response->assertInertia(fn ($page) => $page
        ->where('auth.user.can_scan_gate', true)
        ->where('auth.user.is_organizer', false)
    );
});
