<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\OrganizerProfile;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;

/**
 * Alur pemesanan pengunjung (FR-BKG-01…09).
 *
 * AC-1 Fase 5: dua pengguna memperebutkan kuota terakhir → satu berhasil,
 * satu ditolak dengan pesan yang jelas.
 */
beforeEach(function (): void {
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

    $this->user = User::factory()->create();
});

/**
 * Payload checkout standar.
 *
 * @return array<string, mixed>
 */
function bookingPayload(VisitSlot $slot, TicketType $ticketType, int $quantity = 2): array
{
    return [
        'visit_slot_id' => $slot->id,
        'contact_name' => 'Budi Santoso',
        'contact_email' => 'budi@example.test',
        'contact_phone' => '081234567890',
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => $quantity],
        ],
    ];
}

test('a visitor can create a booking and quota is held', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType, 3));

    $booking = Booking::query()->firstOrFail();

    $response->assertRedirect(route('bookings.show', $booking->uuid));

    expect($booking->status)->toBe(BookingStatus::PENDING_PAYMENT)
        ->and($booking->total_amount)->toBe(150000)
        ->and($booking->user_id)->toBe($this->user->id)
        // BR-05: kuota ditahan, belum terjual.
        ->and($this->slot->fresh()->reserved_count)->toBe(3)
        ->and($this->slot->fresh()->sold_count)->toBe(0)
        ->and($this->slot->fresh()->remaining())->toBe(7);

    expect(BookingItem::query()->count())->toBe(1);

    // BR-06: batas bayar = sekarang + payment_window_minutes.
    expect($booking->expires_at)->not->toBeNull()
        ->and((int) round(now()->diffInMinutes($booking->expires_at, false)))->toBe(15);
});

test('AC-1: when two users compete for the last quota, exactly one succeeds', function (): void {
    $this->slot->update(['capacity' => 2, 'reserved_count' => 0, 'sold_count' => 0]);

    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType, 2))
        ->assertRedirect();

    // Dokumen menuntut 409 untuk kuota habis (§12.1 langkah 10).
    $this->actingAs($second)
        ->postJson(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType, 2))
        ->assertStatus(409)
        ->assertJsonPath('remaining', 0);

    expect(Booking::query()->count())->toBe(1)
        ->and($this->slot->fresh()->reserved_count)->toBe(2)
        ->and($this->slot->fresh()->remaining())->toBe(0);
});

test('a booking is rejected when total quantity exceeds the museum limit', function (): void {
    // Museum membatasi 5 tiket per pemesanan.
    $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType, 6))
        ->assertSessionHasErrors('quantity');

    expect(Booking::query()->count())->toBe(0)
        ->and($this->slot->fresh()->reserved_count)->toBe(0);
});

test('a booking is rejected for a museum that is not published (BR-01)', function (): void {
    $this->museum->update(['status' => 'draft']);

    $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType))
        ->assertNotFound();

    expect(Booking::query()->count())->toBe(0);
});

test('a booking is rejected for a closed slot', function (): void {
    $this->slot->update(['status' => 'closed']);

    $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType))
        ->assertSessionHasErrors('visit_slot_id');

    expect(Booking::query()->count())->toBe(0);
});

test('a booking is rejected for a past date', function (): void {
    $this->slot->update(['visit_date' => now()->subDay()->toDateString()]);

    $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType))
        ->assertSessionHasErrors('visit_slot_id');
});

test('a booking is rejected beyond the booking window (BR-04)', function (): void {
    AppSetting::set('booking_window_days', '30', 'test');

    $this->slot->update(['visit_date' => now()->addDays(45)->toDateString()]);

    $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType))
        ->assertSessionHasErrors('visit_slot_id');
});

test('a slot belonging to another museum is rejected', function (): void {
    $otherSlot = VisitSlot::factory()->create(['capacity' => 10]);

    $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($otherSlot, $this->ticketType))
        ->assertNotFound();
});

test('an inactive ticket type cannot be booked', function (): void {
    $this->ticketType->update(['is_active' => false]);

    $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType))
        ->assertSessionHasErrors('items');
});

test('cancelling a pending booking releases its quota (FR-BKG-07)', function (): void {
    $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType, 3));

    $booking = Booking::query()->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('bookings.cancel', $booking->uuid))
        ->assertRedirect(route('bookings.show', $booking->uuid));

    expect($booking->fresh()->status)->toBe(BookingStatus::CANCELLED)
        ->and($this->slot->fresh()->reserved_count)->toBe(0)
        ->and($this->slot->fresh()->remaining())->toBe(10);
});

test('cancelling a pending booking also cancels its active payment', function (): void {
    // Payment yang tertinggal `pending` setelah booking dibatalkan tidak akan
    // pernah dibayar, tetapi tetap dihitung `ops:health` sebagai pembayaran
    // menggantung — alarm AC-5 berbunyi palsu terus-menerus.
    $this->actingAs($this->user)
        ->post(route('bookings.store', $this->museum), bookingPayload($this->slot, $this->ticketType, 1));

    $booking = Booking::query()->firstOrFail();

    $payment = Payment::factory()->for($booking)->create([
        'status' => PaymentStatus::PENDING,
    ]);

    $this->actingAs($this->user)
        ->post(route('bookings.cancel', $booking->uuid))
        ->assertRedirect(route('bookings.show', $booking->uuid));

    expect($payment->fresh()->status)->toBe(PaymentStatus::CANCELLED);
});

test('a confirmed booking cannot be cancelled by the visitor', function (): void {
    $booking = Booking::factory()->confirmed()->forSlot($this->slot)->forUser($this->user)->create();
    $this->slot->update(['sold_count' => 2, 'reserved_count' => 0]);

    $this->actingAs($this->user)
        ->post(route('bookings.cancel', $booking->uuid))
        ->assertSessionHas('error');
    expect($booking->fresh()->status)->toBe(BookingStatus::CONFIRMED)
        ->and($this->slot->fresh()->sold_count)->toBe(2);
});

/*
 * Pesan penolakan harus benar-benar sampai ke pengguna.
 *
 * Tombol "Bayar" memakai `router.post`, bukan `useForm`, sehingga
 * `ValidationException` berakhir di `page.props.errors` — dan tidak ada
 * halaman yang merendernya. Akibatnya pengguna yang menekan bayar pada booking
 * yang sudah lunas hanya melihat halaman yang sama tanpa penjelasan apa pun.
 * Karena itu kegagalan dikembalikan sebagai flash, yang dirender halaman.
 */
test('paying a booking that is no longer payable shows a message instead of failing silently', function (): void {
    $booking = Booking::factory()->confirmed()->forSlot($this->slot)->forUser($this->user)->create();

    $response = $this->actingAs($this->user)
        ->post(route('bookings.pay', $booking->uuid));

    $response->assertRedirect(route('bookings.show', $booking->uuid));
    $response->assertSessionHas('error', 'Booking ini tidak dapat dibayar lagi.');
    expect($booking->fresh()->payments()->count())->toBe(0);
});

test('the message from a refused payment is visible on the booking page', function (): void {
    $booking = Booking::factory()->confirmed()->forSlot($this->slot)->forUser($this->user)->create();

    $this->actingAs($this->user)
        ->post(route('bookings.pay', $booking->uuid))
        ->assertSessionHas('error');

    $this->actingAs($this->user)
        ->get(route('bookings.show', $booking->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.error', 'Booking ini tidak dapat dibayar lagi.')
            ->where('booking.is_payable', false)
        );
});

test('a visitor cannot view or cancel another visitor booking', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $booking = Booking::factory()->forSlot($this->slot)->forUser($owner)->create();

    $this->actingAs($intruder)->get(route('bookings.show', $booking->uuid))->assertForbidden();
    $this->actingAs($intruder)->post(route('bookings.cancel', $booking->uuid))->assertForbidden();
});

test('the booking history only lists the visitor own bookings', function (): void {
    $other = User::factory()->create();

    Booking::factory()->forSlot($this->slot)->forUser($this->user)->create();
    Booking::factory()->forSlot($this->slot)->forUser($other)->create();

    $response = $this->actingAs($this->user)->get(route('bookings.index'));

    $response->assertOk();
    expect($response->viewData('page')['props']['bookings']['data'])->toHaveCount(1);
});

test('AC-5: the expire command releases quota and marks the booking expired', function (): void {
    $booking = Booking::factory()
        ->forSlot($this->slot)
        ->forUser($this->user)
        ->overdue()
        ->create();

    BookingItem::factory()->forBooking($booking)->quantity(3)->create();
    $this->slot->update(['reserved_count' => 3]);

    $this->artisan('bookings:expire')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::EXPIRED)
        ->and($this->slot->fresh()->reserved_count)->toBe(0)
        ->and($this->slot->fresh()->remaining())->toBe(10);
});

test('the expire command is idempotent and does not release quota twice', function (): void {
    $booking = Booking::factory()->forSlot($this->slot)->forUser($this->user)->overdue()->create();
    BookingItem::factory()->forBooking($booking)->quantity(3)->create();
    $this->slot->update(['reserved_count' => 3]);

    $this->artisan('bookings:expire')->assertSuccessful();
    $this->artisan('bookings:expire')->assertSuccessful();

    expect($this->slot->fresh()->reserved_count)->toBe(0);
});

test('the expire command also expires the pending payment', function (): void {
    // Tanpa ini payment tetap `pending` selamanya setelah booking kedaluwarsa:
    // antrean anomali admin terisi pembayaran yang tidak mungkin dibayar, dan
    // `ops:health` melaporkan "pembayaran menggantung" terus-menerus sehingga
    // alarm AC-5 kehilangan maknanya.
    $booking = Booking::factory()->forSlot($this->slot)->forUser($this->user)->overdue()->create();
    BookingItem::factory()->forBooking($booking)->quantity(1)->create();
    $this->slot->update(['reserved_count' => 1]);

    $payment = Payment::factory()->for($booking)->create([
        'status' => PaymentStatus::PENDING,
    ]);

    $this->artisan('bookings:expire')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::EXPIRED);
});

test('the expire command leaves a paid payment untouched', function (): void {
    // Pembayaran yang sudah lunas tidak boleh diturunkan menjadi `expired`:
    // uangnya sudah masuk, dan BR-23 mengatur kasus ini secara terpisah.
    $booking = Booking::factory()->forSlot($this->slot)->forUser($this->user)->overdue()->create();
    BookingItem::factory()->forBooking($booking)->quantity(1)->create();
    $this->slot->update(['reserved_count' => 1]);

    $payment = Payment::factory()->for($booking)->create([
        'status' => PaymentStatus::PAID,
    ]);

    $this->artisan('bookings:expire')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID);
});

test('the expire command ignores bookings that are still within the window', function (): void {
    $booking = Booking::factory()->forSlot($this->slot)->forUser($this->user)->create();
    BookingItem::factory()->forBooking($booking)->quantity(2)->create();
    $this->slot->update(['reserved_count' => 2]);

    $this->artisan('bookings:expire')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT)
        ->and($this->slot->fresh()->reserved_count)->toBe(2);
});
