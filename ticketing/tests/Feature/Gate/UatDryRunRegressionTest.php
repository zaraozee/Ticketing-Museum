<?php

use App\Enums\OrganizerStatus;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Museum;
use App\Models\MuseumStaff;
use App\Models\OrganizerProfile;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Temuan gladi bersih UAT (bukan UAT resmi)
|--------------------------------------------------------------------------
|
| Ketiga kasus di bawah ditemukan saat menjalankan skenario `UAT_PLAN.md` §3
| di lingkungan lokal. Semuanya tidak tertangkap tes yang sudah ada karena
| menuntut kombinasi peran dan keadaan yang belum pernah diuji: petugas gate
| yang bukan organizer, pengunjung yang menekan tombol pada pesanan yang sudah
| tidak dapat dibayar, dan pengelola tanpa profil organisasi.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->organizer = User::factory()->create(['email_verified_at' => now()]);
    $this->organizer->assignRole(UserRole::ORGANIZER->value);

    $this->profile = OrganizerProfile::factory()->for($this->organizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $this->museum = Museum::factory()->forOrganizer($this->profile)->published()->create();

    $this->slot = VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => now()->addDays(5)->toDateString(),
        'capacity' => 10,
        'reserved_count' => 0,
        'sold_count' => 0,
    ]);
});

/*
 * B1 — petugas gate tidak dapat membuka halaman pemindaian.
 *
 * Petugas gate memakai peran `user` dengan baris `museum_staff` aktif
 * (SOP_GATE.md §1). Rute scanner sempat dijaga `role:organizer`, sehingga
 * orang yang justru bertugas memindai tiket mendapat 403 — sementara API
 * `/api/v1/gate/*` sudah menerimanya.
 */
test('a gate staff member can open the scanner page', function (): void {
    $petugas = User::factory()->create(['email_verified_at' => now()]);
    $petugas->assignRole(UserRole::USER->value);

    MuseumStaff::create([
        'museum_id' => $this->museum->id,
        'user_id' => $petugas->id,
        'staff_role' => StaffRole::GATE_STAFF,
        'is_active' => true,
    ]);

    $this->actingAs($petugas)
        ->get('/organizer/scanner')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Organizer/Gate/Scanner')
            ->has('museums', 1)
            ->where('museums.0.id', $this->museum->id)
        );

    $this->actingAs($petugas)
        ->get('/organizer/scans')
        ->assertOk();
});

test('gate staff access stops at the gate pages', function (): void {
    // Membuka scanner bukan berarti boleh melihat laporan berisi data pribadi
    // pemesan atau mengubah data museum.
    $petugas = User::factory()->create(['email_verified_at' => now()]);
    $petugas->assignRole(UserRole::USER->value);

    MuseumStaff::create([
        'museum_id' => $this->museum->id,
        'user_id' => $petugas->id,
        'staff_role' => StaffRole::GATE_STAFF,
        'is_active' => true,
    ]);

    $this->actingAs($petugas)->get('/organizer')->assertForbidden();
    $this->actingAs($petugas)->get('/organizer/reports')->assertForbidden();
    $this->actingAs($petugas)->get('/organizer/museums')->assertForbidden();
});

/*
 * A7 — menekan "Bayar" pada pesanan yang tidak dapat dibayar gagal diam-diam.
 *
 * Tombol bayar memakai `router.post`, bukan `useForm`, sehingga
 * `ValidationException` berakhir di `page.props.errors` yang tidak dirender
 * halaman mana pun. Pengunjung hanya melihat halaman yang sama tanpa
 * penjelasan — termasuk pada skenario UAT A7 yang secara eksplisit meminta
 * "ditolak dengan pesan jelas, bukan error".
 */
test('a visitor sees why a payment was refused', function (): void {
    $pengunjung = User::factory()->create(['email_verified_at' => now()]);
    $pengunjung->assignRole(UserRole::USER->value);

    $booking = Booking::factory()->confirmed()->forSlot($this->slot)->forUser($pengunjung)->create();

    $this->actingAs($pengunjung)
        ->post(route('bookings.pay', $booking->uuid))
        ->assertSessionHas('error', 'Booking ini tidak dapat dibayar lagi.');

    $this->actingAs($pengunjung)
        ->get(route('bookings.show', $booking->uuid))
        ->assertInertia(fn ($page) => $page->where('flash.error', 'Booking ini tidak dapat dibayar lagi.'));
});

/*
 * C4 — ekspor laporan 500 untuk pengelola tanpa profil organisasi.
 *
 * `index()` menangani keadaan itu dengan paginator kosong, sedangkan `export()`
 * memakai `$profile->id` tanpa pemeriksaan. Akibatnya halaman laporan terbuka
 * tetapi tombol ekspor berakhir 500.
 */
test('exporting a report without an organizer profile returns 404 not 500', function (): void {
    $tanpaProfil = User::factory()->create(['email_verified_at' => now()]);
    $tanpaProfil->assignRole(UserRole::ORGANIZER->value);

    $this->actingAs($tanpaProfil)->get('/organizer/reports')->assertOk();
    $this->actingAs($tanpaProfil)->get('/organizer/reports/export?museum_id='.$this->museum->id)->assertNotFound();
});

test('the export contains only the organizer own museum data', function (): void {
    $pengunjung = User::factory()->create(['email_verified_at' => now()]);
    $pengunjung->assignRole(UserRole::USER->value);

    $booking = Booking::factory()->confirmed()->forSlot($this->slot)->forUser($pengunjung)->create([
        'museum_id' => $this->museum->id,
        'total_amount' => 75000,
    ]);

    $lain = Museum::factory()->forOrganizer(
        OrganizerProfile::factory()->approved()->create()
    )->published()->create();

    Booking::factory()->confirmed()->forUser($pengunjung)->create([
        'museum_id' => $lain->id,
        'total_amount' => 999000,
    ]);

    $respons = $this->actingAs($this->organizer)
        ->get('/organizer/reports/export?museum_id='.$this->museum->id);

    $respons->assertOk();

    $csv = $respons->streamedContent();

    expect($csv)->toContain($booking->booking_code)
        ->and($csv)->toContain('75000')
        ->and($csv)->not->toContain('999000');
});

test('an organizer cannot export another museum report', function (): void {
    $lain = Museum::factory()->forOrganizer(
        OrganizerProfile::factory()->approved()->create()
    )->published()->create();

    $this->actingAs($this->organizer)
        ->get('/organizer/reports/export?museum_id='.$lain->id)
        ->assertNotFound();
});

test('the scanner lists both owned and staffed museums without duplicates', function (): void {
    /*
     * `merge()` pada koleksi Eloquent menuntut koleksi model. Memetakan ke
     * array sebelum merge membuat halaman scanner gagal 500 dengan
     * "Call to a member function getKey() on array" — bug yang baru terlihat
     * setelah petugas gate dapat membuka halaman ini.
     */
    $petugas = User::factory()->create(['email_verified_at' => now()]);
    $petugas->assignRole(UserRole::USER->value);

    MuseumStaff::create([
        'museum_id' => $this->museum->id,
        'user_id' => $petugas->id,
        'staff_role' => StaffRole::GATE_STAFF,
        'is_active' => true,
    ]);

    // Museum kedua yang juga dikelola petugas yang sama.
    $kedua = Museum::factory()->forOrganizer($this->profile)->published()->create();
    MuseumStaff::create([
        'museum_id' => $kedua->id,
        'user_id' => $petugas->id,
        'staff_role' => StaffRole::GATE_STAFF,
        'is_active' => true,
    ]);

    // Museum yang tidak boleh terlihat.
    Museum::factory()->forOrganizer(
        OrganizerProfile::factory()->approved()->create()
    )->published()->create();

    $this->actingAs($petugas)
        ->get('/organizer/scanner')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('museums', 2)
            ->where('museums.0.name', fn ($nama) => in_array($nama, [$this->museum->name, $kedua->name], true))
        );
});

test('a deactivated staff row revokes scanner access', function (): void {
    $petugas = User::factory()->create(['email_verified_at' => now()]);
    $petugas->assignRole(UserRole::USER->value);

    MuseumStaff::create([
        'museum_id' => $this->museum->id,
        'user_id' => $petugas->id,
        'staff_role' => StaffRole::GATE_STAFF,
        'is_active' => false,
    ]);

    $this->actingAs($petugas)->get('/organizer/scanner')->assertForbidden();
});

test('a closed date blocks booking on that date', function (): void {
    // Skenario C3: menandai tanggal tutup harus benar-benar menolak pemesanan,
    // bukan sekadar menampilkan label di kalender. Sebelumnya `BookingService`
    // hanya memeriksa status slot dan jendela waktu — tanggal tutup hanya
    // dipakai saat membuat slot dan menampilkan kalender, sehingga slot yang
    // sudah terlanjur dibuat tetap dapat dipesan.
    $tanggal = now()->addDays(6)->toDateString();

    $slotTutup = VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => $tanggal,
        'capacity' => 10,
        'reserved_count' => 0,
        'sold_count' => 0,
    ]);

    $this->museum->closedDates()->create(['closed_on' => $tanggal]);

    $pengunjung = User::factory()->create(['email_verified_at' => now()]);
    $pengunjung->assignRole(UserRole::USER->value);

    $tipe = TicketType::factory()->create(['museum_id' => $this->museum->id, 'is_active' => true]);

    $this->actingAs($pengunjung)
        ->post(route('bookings.store', $this->museum), [
            'visit_slot_id' => $slotTutup->id,
            'contact_name' => 'Pengunjung',
            'contact_email' => 'pengunjung@test.com',
            'contact_phone' => '081234567890',
            'items' => [['ticket_type_id' => $tipe->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('visit_slot_id');

    expect(Booking::where('visit_slot_id', $slotTutup->id)->count())->toBe(0);
});

test('a slot on a normal date can still be booked', function (): void {
    // Penolakan tanggal tutup tidak boleh memengaruhi tanggal biasa.
    $tanggal = now()->addDays(7)->toDateString();

    $slot = VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => $tanggal,
        'capacity' => 10,
        'reserved_count' => 0,
        'sold_count' => 0,
    ]);

    $pengunjung = User::factory()->create(['email_verified_at' => now()]);
    $pengunjung->assignRole(UserRole::USER->value);

    $tipe = TicketType::factory()->create(['museum_id' => $this->museum->id, 'is_active' => true]);

    $this->actingAs($pengunjung)
        ->post(route('bookings.store', $this->museum), [
            'visit_slot_id' => $slot->id,
            'contact_name' => 'Pengunjung',
            'contact_email' => 'pengunjung@test.com',
            'contact_phone' => '081234567890',
            'items' => [['ticket_type_id' => $tipe->id, 'quantity' => 1]],
        ])
        ->assertRedirect();

    expect(Booking::where('visit_slot_id', $slot->id)->count())->toBe(1);
});
