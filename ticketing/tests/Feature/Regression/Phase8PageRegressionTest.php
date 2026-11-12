<?php

use App\Enums\MuseumStatus;
use App\Enums\OrganizerStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\OrganizerProfile;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Regresi Fase 8 — halaman yang sempat gagal saat verifikasi manual
|--------------------------------------------------------------------------
|
| Ketiga bug di bawah ditemukan lewat uji browser (DevTools/Lighthouse), bukan
| oleh unit test, sehingga didaftarkan agar tidak muncul kembali.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    // Bekukan waktu agar rentang laporan (default: awal bulan s.d. hari ini)
    // tidak bergantung pada jam dinding saat suite dijalankan.
    $this->travelTo(Carbon::parse('2026-03-10 12:00:00', 'Asia/Jakarta'));
});

afterEach(function (): void {
    $this->travelBack();
});

function userWithRole(UserRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * Booking terkonfirmasi lengkap dengan item dan satu tiket aktif.
 */
function confirmedBookingFor(Museum $museum, User $user, int $hour = 9): Booking
{
    $museum->update(['booking_mode' => 'timed_slot']);

    // Slot unik per museum+tanggal+jam (unique index `visit_slots_unique_slot`),
    // jadi tiap pemanggilan dalam satu museum memakai jam berbeda.
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

    $item = BookingItem::factory()
        ->for($booking)
        ->for($ticketType)
        ->create(['quantity' => 1]);

    $uuid = (string) Str::uuid();

    Ticket::create([
        'booking_id' => $booking->id,
        'booking_item_id' => $item->id,
        'museum_id' => $museum->id,
        'visit_slot_id' => $slot->id,
        'uuid' => $uuid,
        'ticket_code' => Ticket::generateTicketCode(),
        'token_hash' => hash('sha256', 'test-token-'.$uuid),
        'status' => 'active',
        'sequence' => 1,
    ]);

    return $booking->fresh(['tickets', 'museum', 'visitSlot']);
}

test('the my tickets page only lists tickets owned by the signed in user', function (): void {
    $user = userWithRole(UserRole::USER);
    $otherUser = userWithRole(UserRole::USER);

    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);

    $ownTicket = confirmedBookingFor($museum, $user)->tickets->first();
    confirmedBookingFor($museum, $otherUser, 10);

    $response = $this->actingAs($user)->get('/tickets');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Tickets/Index')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.ticket_code', $ownTicket->ticket_code)
        ->where('tickets.data.0.museum_name', $museum->name)
    );
});

test('the ticket detail page shows the visit date from the booking snapshot', function (): void {
    $user = userWithRole(UserRole::USER);
    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);

    $ticket = confirmedBookingFor($museum, $user)->tickets->first();

    $response = $this->actingAs($user)->get('/tickets/'.$ticket->uuid);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Tickets/Show')
        ->where('ticket.uuid', $ticket->uuid)
        ->where('ticket.slot_label', '09:00 - 10:00')
        ->where('ticket.visit_date', $ticket->booking->visit_date_snapshot->format('d/m/Y'))
    );
});

test('a signed in user cannot open a ticket belonging to someone else', function (): void {
    $user = userWithRole(UserRole::USER);
    $otherUser = userWithRole(UserRole::USER);

    $museum = Museum::factory()->create(['status' => MuseumStatus::PUBLISHED]);
    $foreignTicket = confirmedBookingFor($museum, $otherUser)->tickets->first();

    $this->actingAs($user)
        ->get('/tickets/'.$foreignTicket->uuid)
        ->assertNotFound();
});

test('the admin report page lists revenue per museum without a sqlite HAVING error', function (): void {
    $admin = userWithRole(UserRole::ADMIN);
    $visitor = userWithRole(UserRole::USER);

    $museum = Museum::factory()->create([
        'name' => 'Museum Pendapatan',
        'status' => MuseumStatus::PUBLISHED,
    ]);

    confirmedBookingFor($museum, $visitor);

    // Museum tanpa transaksi tidak boleh muncul di daftar pendapatan.
    Museum::factory()->create([
        'name' => 'Museum Tanpa Transaksi',
        'status' => MuseumStatus::PUBLISHED,
    ]);

    $response = $this->actingAs($admin)->get('/admin/reports');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Reports/Index')
        ->has('revenueByMuseum', 1)
        ->where('revenueByMuseum.0.name', 'Museum Pendapatan')
        ->where('revenueByMuseum.0.revenue', 150000)
    );
});

test('the organizer report page renders when the organizer owns no museum', function (): void {
    $organizer = userWithRole(UserRole::ORGANIZER);

    $response = $this->actingAs($organizer)->get('/organizer/reports');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Organizer/Reports/Index')
        ->where('selectedMuseum', null)
        ->has('bookings.data', 0)
    );
});

test('the organizer report page rejects a museum owned by another organizer', function (): void {
    $organizer = userWithRole(UserRole::ORGANIZER);

    $otherOrganizer = userWithRole(UserRole::ORGANIZER);
    $otherProfile = OrganizerProfile::factory()->for($otherOrganizer)->create([
        'status' => OrganizerStatus::APPROVED,
    ]);

    $foreignMuseum = Museum::factory()->create([
        'organizer_profile_id' => $otherProfile->id,
        'status' => MuseumStatus::PUBLISHED,
    ]);

    $this->actingAs($organizer)
        ->get('/organizer/reports?museum_id='.$foreignMuseum->id)
        ->assertForbidden();
});
