<?php

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\OrganizerProfile;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\URL;

/**
 * Penegakan verifikasi email pada transaksi tiket (FR-AUTH-02).
 *
 * Pemesanan tiket adalah komitmen finansial dan e-tiket dikirim ke alamat
 * email kontak. Menerima pesanan dari alamat yang belum terbukti milik
 * pendaftar berarti tiket dapat diklaim orang lain, dan pengingat kunjungan
 * dapat dikirim ke alamat yang salah.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $category = MuseumCategory::factory()->create();
    $profile = OrganizerProfile::factory()->approved()->create();

    $this->museum = Museum::factory()
        ->forOrganizer($profile)
        ->published()
        ->create(['category_id' => $category->id, 'max_tickets_per_booking' => 5]);

    $this->slot = VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => now()->addDays(7)->toDateString(),
        'capacity' => 10,
        'reserved_count' => 0,
        'sold_count' => 0,
    ]);

    $this->ticketType = TicketType::factory()->create([
        'museum_id' => $this->museum->id,
        'name' => 'Tiket Dewasa',
        'price' => 50000,
        'is_active' => true,
    ]);

    $this->unverified = User::factory()->unverified()->create();
    $this->unverified->assignRole(UserRole::USER->value);

    $this->verified = User::factory()->create();
    $this->verified->assignRole(UserRole::USER->value);
});

test('an unverified visitor is sent to the verification notice instead of booking pages', function (string $method, string $routeName): void {
    $url = $routeName === 'bookings.store'
        ? route($routeName, $this->museum)
        : route($routeName, $routeName === 'bookings.create' ? $this->museum : null);

    $this->actingAs($this->unverified)
        ->call($method, $url)
        ->assertRedirect(route('verification.notice', absolute: false));
})->with([
    ['GET', 'bookings.index'],
    ['GET', 'bookings.create'],
    ['POST', 'bookings.store'],
    ['GET', 'tickets.index'],
]);

test('an unverified visitor cannot create a booking', function (): void {
    $this->actingAs($this->unverified)
        ->post(route('bookings.store', $this->museum), [
            'visit_slot_id' => $this->slot->id,
            'contact_name' => 'Budi Santoso',
            'contact_email' => 'budi@example.test',
            'contact_phone' => '081234567890',
            'items' => [
                ['ticket_type_id' => $this->ticketType->id, 'quantity' => 2],
            ],
        ])
        ->assertRedirect(route('verification.notice', absolute: false));

    // Middleware harus menolak sebelum controller berjalan; kalau kuota sempat
    // ditahan lalu transaksi dibatalkan, kuota bisa terkunci tanpa booking.
    expect(Booking::count())->toBe(0)
        ->and($this->slot->fresh()->reserved_count)->toBe(0);
});

test('a verified visitor can reach the booking pages', function (): void {
    $this->actingAs($this->verified)->get(route('bookings.index'))->assertOk();
    $this->actingAs($this->verified)->get(route('bookings.create', $this->museum))->assertOk();
    $this->actingAs($this->verified)->get(route('tickets.index'))->assertOk();
});

test('an unverified visitor can still manage their profile and notifications', function (): void {
    // Profil dan notifikasi justru dibutuhkan ketika verifikasi belum selesai,
    // dan keduanya tidak menimbulkan kewajiban finansial.
    $this->actingAs($this->unverified)->get(route('profile.edit'))->assertOk();
    $this->actingAs($this->unverified)->get(route('notifications.index'))->assertOk();
    $this->actingAs($this->unverified)->get(route('verification.notice'))->assertOk();
});

test('an ajax request from an unverified visitor is redirected rather than aborted', function (): void {
    // Klien Inertia mengirim X-Requested-With dengan Accept: text/html. Bila
    // middleware salah jalur ke abort(403), pengunjung melihat halaman error
    // alih-alih diminta memverifikasi email. Header X-Inertia sengaja tidak
    // disertakan: itu memicu pemeriksaan versi aset dan menutupi perilaku
    // middleware yang sedang diuji.
    $this->actingAs($this->unverified)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])
        ->get(route('bookings.index'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('a guest is still sent to login before the verification check', function (): void {
    $this->get(route('bookings.index'))->assertRedirect('/login');
    $this->get(route('tickets.index'))->assertRedirect('/login');
});

test('the verification notice remembers the page the visitor wanted', function (): void {
    $this->actingAs($this->unverified)->get(route('bookings.index'));

    expect(session('url.intended'))->toContain('/bookings');
});

test('a verified user is not sent to the verification notice', function (): void {
    $this->actingAs($this->verified)
        ->get(route('bookings.index'))
        ->assertOk()
        ->assertSessionMissing('url.intended');
});

test('the ticket QR token endpoint is not reachable by an unverified visitor', function (): void {
    // Token QR adalah kunci masuk museum; harus dilindungi sama ketatnya
    // dengan halaman tiket itu sendiri. Untuk permintaan JSON, middleware
    // menjawab 403 - bukan redirect - dan itu memang perilaku yang benar.
    $booking = Booking::factory()->create([
        'user_id' => $this->unverified->id,
        'museum_id' => $this->museum->id,
        'visit_slot_id' => $this->slot->id,
    ]);
    $ticket = Ticket::factory()->create(['booking_id' => $booking->id]);

    $this->actingAs($this->unverified)
        ->getJson(route('api.v1.tickets.token', $ticket->uuid))
        ->assertForbidden();

    // Kendali positif: pemilik sah yang sudah terverifikasi tetap dapat
    // mengambil tokennya. Tanpa ini, tes hanya membuktikan "selalu ditolak".
    $this->unverified->markEmailAsVerified();

    $this->actingAs($this->unverified->fresh())
        ->getJson(route('api.v1.tickets.token', $ticket->uuid))
        ->assertOk()
        ->assertJsonStructure(['qr_token', 'ticket_code', 'status']);
});

test('after verifying, the visitor returns to the booking page they wanted', function (): void {
    // Alur nyata: pengunjung menekan "Pesan Tiket", dialihkan ke halaman
    // verifikasi, membuka tautan di email, lalu harus kembali ke pemesanan -
    // bukan dibuang ke beranda sehingga harus mencari museumnya lagi.
    $this->actingAs($this->unverified)->get(route('bookings.index'));

    expect(session('url.intended'))->toContain('/bookings');

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $this->unverified->id, 'hash' => sha1($this->unverified->email)]
    );

    $this->actingAs($this->unverified)
        ->get($verificationUrl)
        ->assertRedirect(route('bookings.index', absolute: false));

    expect($this->unverified->fresh()->hasVerifiedEmail())->toBeTrue();

    // Dan sekarang halaman itu benar-benar terbuka.
    $this->actingAs($this->unverified->fresh())
        ->get(route('bookings.index'))
        ->assertOk();
});
