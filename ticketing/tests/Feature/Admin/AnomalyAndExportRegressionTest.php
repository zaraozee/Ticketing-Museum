<?php

use App\Enums\BookingStatus;
use App\Enums\MuseumStatus;
use App\Enums\OrganizerStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\OrganizerProfile;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Regresi halaman yang diuji manual pada UAT (AC-6)
|--------------------------------------------------------------------------
|
| Dua skenario UAT sebelumnya tidak punya jaring pengaman otomatis:
|
|   C4 — ekspor laporan penjualan menghasilkan berkas yang angkanya sesuai
|   D1 — halaman antrean anomali admin terbuka
|   D2 — transaksi normal tidak masuk antrean anomali
|
| Ketiganya adalah halaman yang hanya diuji dengan mata saat UAT berlangsung,
| sehingga kesalahan kecil (kolom tertukar, transaksi normal ikut tertandai)
| baru ketahuan di depan museum pilot. Tes ini memindahkan pemeriksaan itu ke
| suite agar sesi UAT tidak dipakai untuk menemukan bug yang bisa dicegah.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    // Rentang laporan default adalah awal bulan s.d. hari ini; waktu dibekukan
    // agar hasilnya tidak bergantung pada jam dinding saat suite dijalankan.
    $this->travelTo(Carbon::parse('2026-03-10 12:00:00', 'Asia/Jakarta'));
});

afterEach(function (): void {
    $this->travelBack();
});

function userWithRoleForUat(UserRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * Booking terkonfirmasi dengan item dan satu tiket aktif.
 */
function uatBooking(Museum $museum, User $user, int $hour = 9): Booking
{
    $museum->update(['booking_mode' => 'timed_slot']);

    $slot = VisitSlot::factory()->for($museum)->create([
        'visit_date' => now()->toDateString(),
        'start_time' => sprintf('%02d:00:00', $hour),
        'end_time' => sprintf('%02d:00:00', $hour + 1),
    ]);

    $booking = Booking::factory()
        ->for($user)
        ->for($museum)
        ->for($slot, 'visitSlot')
        ->confirmed()
        ->create(['total_amount' => 150000]);

    $ticketType = TicketType::factory()->for($museum)->create();

    BookingItem::factory()
        ->for($booking)
        ->for($ticketType)
        ->create(['quantity' => 2]);

    return $booking->fresh(['items', 'museum', 'user']);
}

/**
 * Membaca isi CSV hasil stream sebagai baris-baris.
 *
 * @return array<int, array<int, string>>
 */
function readCsv(string $body): array
{
    // BOM UTF-8 untuk Excel ikut terbawa pada baris pertama.
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;

    $lines = array_values(array_filter(explode("\n", trim($body)), fn (string $l): bool => trim($l) !== ''));

    return array_map(fn (string $line): array => str_getcsv($line, ',', '"', '\\'), $lines);
}

test('the organizer report export returns a csv with the booking numbers for that museum', function (): void {
    $organizer = userWithRoleForUat(UserRole::ORGANIZER);

    $profile = OrganizerProfile::factory()->for($organizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $museum = Museum::factory()->create([
        'organizer_profile_id' => $profile->id,
        'status' => MuseumStatus::PUBLISHED,
    ]);

    $booking = uatBooking($museum, userWithRoleForUat(UserRole::USER));

    $response = $this->actingAs($organizer)
        ->get('/organizer/reports/export?museum_id='.$museum->id);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = readCsv($response->streamedContent());

    expect($csv)->toHaveCount(2);
    expect($csv[0])->toBe([
        'Kode Booking', 'Pemesan', 'Email', 'Tanggal Kunjungan',
        'Status', 'Jumlah Tiket', 'Total (Rp)', 'Dibuat Pada',
    ]);

    // Angka pada berkas harus sama dengan transaksi yang benar-benar terjadi.
    expect($csv[1][0])->toBe($booking->booking_code);
    expect($csv[1][5])->toBe('2');
    expect($csv[1][6])->toBe('150000');
});

test('the organizer report export only contains confirmed bookings of the requested range', function (): void {
    $organizer = userWithRoleForUat(UserRole::ORGANIZER);

    $profile = OrganizerProfile::factory()->for($organizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $museum = Museum::factory()->create([
        'organizer_profile_id' => $profile->id,
        'status' => MuseumStatus::PUBLISHED,
    ]);

    $confirmed = uatBooking($museum, userWithRoleForUat(UserRole::USER));

    // Booking yang belum dibayar tidak boleh ikut terhitung sebagai penjualan.
    Booking::factory()
        ->for(userWithRoleForUat(UserRole::USER))
        ->for($museum)
        ->create(['status' => BookingStatus::PENDING_PAYMENT, 'total_amount' => 999999]);

    $csv = readCsv($this->actingAs($organizer)
        ->get('/organizer/reports/export?museum_id='.$museum->id)
        ->streamedContent());

    expect($csv)->toHaveCount(2);
    expect($csv[1][0])->toBe($confirmed->booking_code);
});

test('the organizer report export refuses a museum owned by another organizer', function (): void {
    $organizer = userWithRoleForUat(UserRole::ORGANIZER);

    OrganizerProfile::factory()->for($organizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $otherOrganizer = userWithRoleForUat(UserRole::ORGANIZER);
    $otherProfile = OrganizerProfile::factory()->for($otherOrganizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $foreignMuseum = Museum::factory()->create([
        'organizer_profile_id' => $otherProfile->id,
        'status' => MuseumStatus::PUBLISHED,
    ]);

    // Ekspor memuat data pribadi pemesan; kepemilikan wajib diperiksa ulang
    // di sini, bukan hanya di halaman tampilan.
    $this->actingAs($organizer)
        ->get('/organizer/reports/export?museum_id='.$foreignMuseum->id)
        ->assertNotFound();
});

test('the admin report export includes the museum name for each booking', function (): void {
    $admin = userWithRoleForUat(UserRole::ADMIN);

    $museum = Museum::factory()->create([
        'name' => 'Museum Ekspor Uji',
        'status' => MuseumStatus::PUBLISHED,
    ]);

    $booking = uatBooking($museum, userWithRoleForUat(UserRole::USER));

    $response = $this->actingAs($admin)->get('/admin/reports/export');

    $response->assertOk();

    $csv = readCsv($response->streamedContent());

    expect($csv[0])->toBe([
        'Kode Booking', 'Museum', 'Pemesan', 'Email',
        'Tanggal Kunjungan', 'Jumlah Tiket', 'Total (Rp)', 'Dibuat Pada',
    ]);

    expect($csv[1][0])->toBe($booking->booking_code);
    expect($csv[1][1])->toBe('Museum Ekspor Uji');
});

test('the admin anomalies page opens and lists payments flagged for review', function (): void {
    $admin = userWithRoleForUat(UserRole::ADMIN);

    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);
    $booking = uatBooking($museum, userWithRoleForUat(UserRole::USER));

    Payment::factory()
        ->forBooking($booking)
        ->paid()
        ->needsReview()
        ->create();

    $response = $this->actingAs($admin)->get('/admin/anomalies');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Anomalies/Index')
        ->has('anomalies.data', 1)
        ->where('anomalies.data.0.booking_code', $booking->booking_code)
        ->where('anomalies.data.0.needs_review', true)
    );
});

test('a normal paid transaction never appears in the admin anomaly queue', function (): void {
    // Skenario D2 UAT: antrean anomali harus tetap kosong pada transaksi wajar.
    // Bila tidak, petugas admin akan dibanjiri alarm palsu dan berhenti
    // mempercayainya.
    $admin = userWithRoleForUat(UserRole::ADMIN);

    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);
    $booking = uatBooking($museum, userWithRoleForUat(UserRole::USER));

    Payment::factory()->forBooking($booking)->paid()->create();

    $response = $this->actingAs($admin)->get('/admin/anomalies');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Anomalies/Index')
        ->has('anomalies.data', 0)
    );
});

test('a payment for an expired booking is queued as an anomaly', function (): void {
    $admin = userWithRoleForUat(UserRole::ADMIN);

    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);
    $user = userWithRoleForUat(UserRole::USER);

    $slot = VisitSlot::factory()->for($museum)->create([
        'visit_date' => now()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    $booking = Booking::factory()
        ->for($user)
        ->for($museum)
        ->for($slot, 'visitSlot')
        ->expired()
        ->create();

    Payment::factory()->forBooking($booking)->paid()->create();

    $response = $this->actingAs($admin)->get('/admin/anomalies');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Anomalies/Index')
        ->has('anomalies.data', 1)
        ->where('anomalies.data.0.booking_code', $booking->booking_code)
    );
});

test('an admin can mark an anomaly as resolved', function (): void {
    $admin = userWithRoleForUat(UserRole::ADMIN);

    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);
    $booking = uatBooking($museum, userWithRoleForUat(UserRole::USER));

    $payment = Payment::factory()
        ->forBooking($booking)
        ->paid()
        ->needsReview()
        ->create();

    $this->actingAs($admin)
        ->patch('/admin/anomalies/'.$payment->id.'/resolve', [
            'action' => 'mark_resolved',
            'notes' => 'Sudah dicek, pembayaran sah.',
        ])
        ->assertRedirect();

    $payment->refresh();

    expect($payment->needs_review)->toBeFalse();
    expect($payment->review_note)->toBe('Sudah dicek, pembayaran sah.');
});

test('the anomaly resolve endpoint rejects an unknown action', function (): void {
    $admin = userWithRoleForUat(UserRole::ADMIN);

    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);
    $booking = uatBooking($museum, userWithRoleForUat(UserRole::USER));

    $payment = Payment::factory()->forBooking($booking)->paid()->needsReview()->create();

    $this->actingAs($admin)
        ->patch('/admin/anomalies/'.$payment->id.'/resolve', ['action' => 'refund_everything'])
        ->assertSessionHasErrors('action');

    expect($payment->fresh()->needs_review)->toBeTrue();
});

test('the organizer export returns not found instead of a server error without an organizer profile', function (): void {
    /*
     * Bug ditemukan saat menyiapkan UAT (AC-6). Halaman laporan sudah
     * menangani pengelola tanpa profil organisasi dengan paginator kosong,
     * tetapi endpoint ekspornya memakai `$profile->id` langsung sehingga
     * kasus yang sah itu berakhir sebagai HTTP 500.
     *
     * Pengelola tanpa profil dapat terjadi pada akun yang perannya diberikan
     * manual sebelum pendaftaran organisasi selesai.
     */
    $organizer = userWithRoleForUat(UserRole::ORGANIZER);

    $this->actingAs($organizer)
        ->get('/organizer/reports/export?museum_id=1')
        ->assertNotFound();
});

test('the organizer export without a museum id is not found rather than a server error', function (): void {
    $organizer = userWithRoleForUat(UserRole::ORGANIZER);

    OrganizerProfile::factory()->for($organizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $this->actingAs($organizer)
        ->get('/organizer/reports/export')
        ->assertNotFound();
});
