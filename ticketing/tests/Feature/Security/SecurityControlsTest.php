<?php

use App\Enums\BookingStatus;
use App\Enums\MuseumStatus;
use App\Enums\OrganizerStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Museum;
use App\Models\MuseumClosedDate;
use App\Models\OrganizerProfile;
use App\Models\User;
use App\Models\VisitSlot;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Keamanan (Fase 9, SYSTEM_DESIGN §16)
|--------------------------------------------------------------------------
|
| Menguji kontrol yang dapat diverifikasi tanpa infrastruktur eksternal:
| header keamanan, rate limiting, dan penolakan tanggal tutup yang bertabrakan
| dengan booking aktif (skenario 12 §19).
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function securityUser(UserRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role->value);

    return $user;
}

test('every response carries the baseline security headers', function (string $path): void {
    $response = $this->get($path);

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');

    expect($response->headers->get('Permissions-Policy'))
        ->toContain('camera=(self)');
})->with([
    'home' => '/',
    'catalog' => '/museums',
    'login' => '/login',
    'manifest' => '/manifest.webmanifest',
    'service worker' => '/sw.js',
    'offline page' => '/offline',
]);

test('api responses are locked down with a restrictive content security policy', function (): void {
    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);

    $response = $this->getJson("/api/v1/museums/{$museum->slug}/slots");

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'none'");
});

test('hsts is only sent over https', function (): void {
    // Permintaan HTTP biasa (dev/test) tidak boleh mengirim HSTS: browser akan
    // mengabaikannya, dan mengirimnya membuat lokal sulit diakses.
    expect($this->get('/')->headers->get('Strict-Transport-Security'))->toBeNull();

    $secure = $this->get('https://localhost/');

    expect($secure->headers->get('Strict-Transport-Security'))
        ->toContain('max-age=31536000');
});

test('the service worker keeps its root scope header after the security middleware', function (): void {
    $response = $this->get('/sw.js');

    // SecurityHeaders tidak boleh menimpa header khusus ini.
    $response->assertHeader('Service-Worker-Allowed', '/');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('repeated failed logins are throttled', function (): void {
    $limit = (int) config('security.rate_limits.login');

    for ($attempt = 1; $attempt <= $limit; $attempt++) {
        $this->post('/login', [
            'email' => 'attacker@example.test',
            'password' => 'salah-terus',
        ])->assertStatus(302);
    }

    // Percobaan ke-(limit+1) harus ditolak, bukan diproses.
    $this->post('/login', [
        'email' => 'attacker@example.test',
        'password' => 'salah-terus',
    ])->assertStatus(429);
});

test('the login throttle does not lock out a different account from the same ip', function (): void {
    $limit = (int) config('security.rate_limits.login');

    for ($attempt = 1; $attempt <= $limit; $attempt++) {
        $this->post('/login', [
            'email' => 'attacker@example.test',
            'password' => 'salah-terus',
        ]);
    }

    $victim = securityUser(UserRole::USER);

    // Kunci pembatas menggabungkan IP dengan email, sehingga korban tetap
    // dapat masuk dari jaringan yang sama.
    $this->post('/login', [
        'email' => $victim->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($victim);
});

test('registration is throttled per ip', function (): void {
    $limit = (int) config('security.rate_limits.register');

    for ($attempt = 1; $attempt <= $limit; $attempt++) {
        $this->post('/register', [
            'name' => 'Pendaftar '.$attempt,
            'email' => "pendaftar{$attempt}@example.test",
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
    }

    $this->post('/register', [
        'name' => 'Pendaftar Terakhir',
        'email' => 'pendaftar-terakhir@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertStatus(429);
});

test('manual ticket code lookup is throttled separately from qr scanning', function (): void {
    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);
    $staff = securityUser(UserRole::USER);

    $staff->museumStaff()->create([
        'museum_id' => $museum->id,
        'staff_role' => 'gate_staff',
        'is_active' => true,
    ]);

    $max = (int) config('security.rate_limits.scan_manual');

    for ($attempt = 1; $attempt <= $max; $attempt++) {
        $this->actingAs($staff)->postJson('/api/v1/gate/scans', [
            'museum_id' => $museum->id,
            'ticket_code' => 'ZZZZZZZZZ'.$attempt % 10,
            'client_scan_uuid' => (string) Str::uuid(),
        ])->assertStatus(200);
    }

    $this->actingAs($staff)->postJson('/api/v1/gate/scans', [
        'museum_id' => $museum->id,
        'ticket_code' => 'ZZZZZZZZZ9',
        'client_scan_uuid' => (string) Str::uuid(),
    ])->assertStatus(429);
});

test('a closed date cannot be added on a day that already has active bookings', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'Asia/Jakarta'));

    $organizer = securityUser(UserRole::ORGANIZER);
    $profile = OrganizerProfile::factory()->for($organizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $museum = Museum::factory()->create([
        'organizer_profile_id' => $profile->id,
        'status' => MuseumStatus::PUBLISHED,
        'booking_mode' => 'timed_slot',
    ]);

    $visitDate = now()->addDays(7);

    $slot = VisitSlot::factory()->for($museum)->create([
        'visit_date' => $visitDate->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    $visitor = securityUser(UserRole::USER);

    Booking::factory()
        ->for($visitor)
        ->for($museum)
        ->for($slot, 'visitSlot')
        ->confirmed()
        ->create([
            'visit_date_snapshot' => $visitDate->toDateString(),
            'status' => BookingStatus::CONFIRMED,
        ]);

    $response = $this->actingAs($organizer)->post("/organizer/museums/{$museum->slug}/closed-dates", [
        'closed_on' => $visitDate->toDateString(),
        'reason' => 'Perbaikan atap',
    ]);

    $response->assertSessionHasErrors('closed_on');

    expect(MuseumClosedDate::where('museum_id', $museum->id)->count())->toBe(0);

    Carbon::setTestNow();
});

test('a closed date is allowed when the day has no active bookings', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'Asia/Jakarta'));

    $organizer = securityUser(UserRole::ORGANIZER);
    $profile = OrganizerProfile::factory()->for($organizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $museum = Museum::factory()->create([
        'organizer_profile_id' => $profile->id,
        'status' => MuseumStatus::PUBLISHED,
    ]);

    $closedOn = now()->addDays(10)->toDateString();

    $this->actingAs($organizer)
        ->post("/organizer/museums/{$museum->slug}/closed-dates", [
            'closed_on' => $closedOn,
            'reason' => 'Libur nasional',
        ])
        ->assertSessionHasNoErrors();

    expect(MuseumClosedDate::where('museum_id', $museum->id)->whereDate('closed_on', $closedOn)->exists())
        ->toBeTrue();

    Carbon::setTestNow();
});

test('an expired or cancelled booking does not block closing that date', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'Asia/Jakarta'));

    $organizer = securityUser(UserRole::ORGANIZER);
    $profile = OrganizerProfile::factory()->for($organizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $museum = Museum::factory()->create([
        'organizer_profile_id' => $profile->id,
        'status' => MuseumStatus::PUBLISHED,
        'booking_mode' => 'timed_slot',
    ]);

    $visitDate = now()->addDays(7);

    $slot = VisitSlot::factory()->for($museum)->create([
        'visit_date' => $visitDate->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    $visitor = securityUser(UserRole::USER);

    Booking::factory()
        ->for($visitor)
        ->for($museum)
        ->for($slot, 'visitSlot')
        ->create([
            'visit_date_snapshot' => $visitDate->toDateString(),
            'status' => BookingStatus::CANCELLED,
        ]);

    $this->actingAs($organizer)
        ->post("/organizer/museums/{$museum->slug}/closed-dates", [
            'closed_on' => $visitDate->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Injeksi pada komponen query dinamis (§16 "CSRF / XSS / SQLi")
|--------------------------------------------------------------------------
|
| Filter pencarian museum meneruskan input pengguna ke `whereLike`. Nilai itu
| harus diperlakukan sebagai data, bukan potongan SQL.
|
*/
test('a search term containing sql metacharacters is treated as data', function (): void {
    $museum = Museum::factory()->published()->create(['name' => 'Museum Sejarah']);

    // Bila input dirangkai ke SQL, kutip tunggal akan memicu syntax error.
    $response = $this->get('/museums?q='.urlencode("' OR 1=1 --"));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/Museums/Index')
        // Tidak ada hasil: seluruh baris tidak boleh lolos karena injeksi.
        ->has('museums.data', 0));

    expect($museum->fresh()->name)->toBe('Museum Sejarah');
});
