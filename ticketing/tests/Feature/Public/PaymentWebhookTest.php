<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentEventResult;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\OrganizerProfile;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;
use App\Services\Payment\FakeMidtransClient;
use App\Services\Payment\MidtransClient;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Pembayaran dan webhook (FR-PAY-01…08).
 *
 * Gateway diganti `FakeMidtransClient` (D-31) agar tidak ada panggilan
 * jaringan: pengujian harus deterministik.
 */
beforeEach(function (): void {
    $category = MuseumCategory::factory()->create();
    $profile = OrganizerProfile::factory()->approved()->create();

    $this->museum = Museum::factory()
        ->forOrganizer($profile)
        ->published()
        ->create(['category_id' => $category->id]);

    $this->slot = VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => now()->addDays(7)->toDateString(),
        'capacity' => 10,
        'reserved_count' => 2,
        'sold_count' => 0,
    ]);

    $this->ticketType = TicketType::factory()->create([
        'museum_id' => $this->museum->id,
        'name' => 'Tiket Dewasa',
        'price' => 50000,
        'is_active' => true,
    ]);

    $this->user = User::factory()->create();

    $this->booking = Booking::factory()
        ->forSlot($this->slot)
        ->forUser($this->user)
        ->total(100000)
        ->create();

    $this->item = BookingItem::factory()
        ->forBooking($this->booking)
        ->quantity(2)
        ->create([
            'ticket_type_id' => $this->ticketType->id,
            'ticket_name_snapshot' => 'Tiket Dewasa',
            'unit_price' => 50000,
            'subtotal' => 100000,
        ]);

    // Ganti klien dengan gateway palsu agar tes tidak menyentuh jaringan.
    $this->fake = new FakeMidtransClient;
    $this->app->instance(MidtransClient::class, $this->fake);
});

/**
 * Payload notifikasi Midtrans.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function webhookPayload(Payment $payment, array $overrides = []): array
{
    return array_merge([
        'order_id' => $payment->provider_order_id,
        'status_code' => '200',
        'gross_amount' => number_format($payment->gross_amount, 2, '.', ''),
        'transaction_status' => 'settlement',
        'transaction_id' => 'txn-abc-123',
        'payment_type' => 'qris',
        'signature_key' => 'ignored-by-fake',
        'fraud_status' => 'accept',
    ], $overrides);
}

test('starting a payment creates one attempt and asks the gateway for a snap token', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('bookings.pay', $this->booking->uuid));

    $response->assertRedirect(route('bookings.show', $this->booking->uuid));

    $payment = Payment::query()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::PENDING)
        ->and($payment->provider_order_id)->toBe($this->booking->booking_code.'-1')
        ->and($payment->gross_amount)->toBe(100000)
        ->and($payment->snap_token)->not->toBeNull();

    // BR-09: transaksi gateway kedaluwarsa lebih awal dari booking.
    expect($payment->expires_at->lessThan($this->booking->expires_at))->toBeTrue();

    expect($this->fake->snapRequests)->toHaveCount(1);
});

test('starting a payment twice reuses the active attempt instead of creating a second bill', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));

    expect(Payment::query()->count())->toBe(1)
        ->and($this->fake->snapRequests)->toHaveCount(1);
});

test('a confirmed booking cannot be paid again', function (): void {
    $this->booking->update(['status' => BookingStatus::CONFIRMED]);

    $this->actingAs($this->user)
        ->post(route('bookings.pay', $this->booking->uuid))
        ->assertSessionHasErrors('booking');

    expect(Payment::query()->count())->toBe(0);
});

test('a payment attempt is refused when the booking has less than three minutes left (BR-09)', function (): void {
    $this->booking->update(['expires_at' => now()->addMinutes(2)]);

    $this->actingAs($this->user)
        ->post(route('bookings.pay', $this->booking->uuid))
        ->assertSessionHasErrors('booking');
});

test('AC-4: a successful payment confirms the booking, moves quota to sold, and issues tickets', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    $this->fake->forceStatus($payment->provider_order_id, webhookPayload($payment));

    $this->postJson(route('webhooks.midtrans'), webhookPayload($payment))
        ->assertOk()
        ->assertJsonPath('result', PaymentEventResult::APPLIED->value);

    $booking = $this->booking->fresh();

    expect($booking->status)->toBe(BookingStatus::CONFIRMED)
        ->and($booking->confirmed_at)->not->toBeNull()
        // BR-07: kuota pindah dari reserved ke sold dalam transaksi yang sama.
        ->and($this->slot->fresh()->reserved_count)->toBe(0)
        ->and($this->slot->fresh()->sold_count)->toBe(2)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PAID)
        // BR-07: tiket terbit bersamaan dengan konfirmasi.
        ->and(Ticket::query()->where('booking_id', $booking->id)->count())->toBe(2);

    // Tiket aktif dan token asli tidak disimpan.
    $tickets = Ticket::query()->where('booking_id', $booking->id)->get();

    expect($tickets->every(fn (Ticket $ticket): bool => $ticket->status === TicketStatus::ACTIVE))->toBeTrue()
        ->and($tickets->pluck('sequence')->sort()->values()->all())->toBe([1, 2])
        ->and($tickets->pluck('token_hash')->unique())->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| §19 skenario 3: pembayaran berhasil tetapi browser tertutup
|--------------------------------------------------------------------------
|
| Pengguna menutup tab setelah halaman pembayaran terbuka. Tidak ada satu pun
| request dari browser setelah itu; satu-satunya bukti pembayaran adalah
| notifikasi gateway. Yang harus tetap terjadi: booking terkonfirmasi, tiket
| terbit, tiket terlihat di "Tiket Saya", dan email e-ticket masuk antrean.
|
*/
test('scenario 3: a payment completes with the browser closed and the tickets appear in my tickets', function (): void {
    Mail::fake();

    // Pengguna hanya membuka halaman pembayaran, lalu menutup tab.
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    // Sejak titik ini tidak ada request dari browser sama sekali.
    $this->fake->forceStatus($payment->provider_order_id, webhookPayload($payment));

    $this->postJson(route('webhooks.midtrans'), webhookPayload($payment))
        ->assertOk()
        ->assertJsonPath('result', PaymentEventResult::APPLIED->value);

    // E-ticket terkirim lewat antrean, bukan ditunggu di request pengguna.
    Mail::assertQueued(BookingConfirmedMail::class, 1);

    $this->assertDatabaseHas('email_logs', [
        'mailable_type' => BookingConfirmedMail::class,
        'to_email' => $this->booking->contact_email,
        'status' => 'pending',
    ]);

    // Tiket benar-benar dapat dilihat pengguna saat ia membuka aplikasi lagi.
    $this->actingAs($this->user)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Tickets/Index')
            ->has('tickets.data', 2));

    $ticket = Ticket::query()->where('booking_id', $this->booking->id)->firstOrFail();

    $this->actingAs($this->user)
        ->get(route('tickets.show', $ticket->uuid))
        ->assertOk();

    // Notifikasi in-app juga tersedia tanpa perlu menyegarkan halaman pembayaran.
    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->user->id,
        'type' => 'booking.confirmed',
    ]);
});

test('AC-2: a duplicate webhook does not create a second payment or duplicate tickets', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    $this->fake->forceStatus($payment->provider_order_id, webhookPayload($payment));

    $this->postJson(route('webhooks.midtrans'), webhookPayload($payment))->assertOk();
    $second = $this->postJson(route('webhooks.midtrans'), webhookPayload($payment));

    $second->assertOk()->assertJsonPath('result', PaymentEventResult::IGNORED_DUPLICATE->value);

    expect(Payment::query()->count())->toBe(1)
        ->and(PaymentEvent::query()->count())->toBe(1)
        ->and(Ticket::query()->count())->toBe(2)
        ->and($this->slot->fresh()->sold_count)->toBe(2);
});

test('AC-3: an invalid signature returns 403 and changes nothing', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    $this->fake->forceSignature($payment->provider_order_id, false);

    $this->postJson(route('webhooks.midtrans'), webhookPayload($payment))
        ->assertForbidden();

    expect($payment->fresh()->status)->toBe(PaymentStatus::PENDING)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT)
        ->and($this->slot->fresh()->sold_count)->toBe(0)
        ->and(Ticket::query()->count())->toBe(0);

    // Notifikasi yang ditolak tetap tercatat untuk penelusuran.
    expect(PaymentEvent::query()->where('signature_valid', false)->count())->toBe(1);
});

test('an invalid signature cannot poison the idempotency key of the real notification', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    $payload = webhookPayload($payment);

    // Penyerang mengirim payload identik dengan signature palsu lebih dulu.
    $this->fake->forceSignature($payment->provider_order_id, false);
    $this->postJson(route('webhooks.midtrans'), $payload)->assertForbidden();

    // Notifikasi asli dari gateway harus tetap diproses.
    $this->fake->forceSignature($payment->provider_order_id, true);
    $this->fake->forceStatus($payment->provider_order_id, $payload);

    $this->postJson(route('webhooks.midtrans'), $payload)
        ->assertOk()
        ->assertJsonPath('result', PaymentEventResult::APPLIED->value);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED);
});

test('AC-6: a gross amount mismatch flags the payment for review and does not confirm', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    $tampered = webhookPayload($payment, ['gross_amount' => '1000.00']);
    $this->fake->forceStatus($payment->provider_order_id, $tampered);

    $this->postJson(route('webhooks.midtrans'), webhookPayload($payment))
        ->assertOk()
        ->assertJsonPath('result', PaymentEventResult::AMOUNT_MISMATCH->value);

    $payment->refresh();

    expect($payment->needs_review)->toBeTrue()
        ->and($payment->status)->toBe(PaymentStatus::PENDING)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT)
        ->and(Ticket::query()->count())->toBe(0);
});

test('AC-8: the payment status endpoint is read-only and never trusts client input', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    // Klien mencoba "menentukan" status lewat query string.
    $response = $this->actingAs($this->user)->get(route('bookings.status', $this->booking->uuid).'?status=paid&booking_status=confirmed');

    $response->assertOk()
        ->assertJsonPath('booking_status', BookingStatus::PENDING_PAYMENT->value)
        ->assertJsonPath('payment_status', PaymentStatus::PENDING->value);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PENDING);
});

test('a visitor cannot poll the status of another visitor booking', function (): void {
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->getJson(route('bookings.status', $this->booking->uuid))
        ->assertForbidden();
});

test('AC-7 / BR-23: a payment arriving after expiry re-reserves quota when possible', function (): void {
    $this->booking->update([
        'status' => BookingStatus::EXPIRED,
        'expired_at' => now(),
    ]);
    // Kuota sudah dilepas saat booking kedaluwarsa.
    $this->slot->update(['reserved_count' => 0]);

    $payment = Payment::factory()->forBooking($this->booking)->create([
        'status' => PaymentStatus::PENDING,
    ]);

    $this->fake->forceStatus($payment->provider_order_id, webhookPayload($payment));

    $this->postJson(route('webhooks.midtrans'), webhookPayload($payment))->assertOk();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED)
        ->and($this->slot->fresh()->sold_count)->toBe(2)
        ->and($this->slot->fresh()->reserved_count)->toBe(0)
        ->and(Ticket::query()->count())->toBe(2);
});

test('AC-7 / BR-23: a late payment without available quota goes to the anomaly queue', function (): void {
    $this->booking->update(['status' => BookingStatus::EXPIRED, 'expired_at' => now()]);
    // Kuota sudah diambil pengunjung lain.
    $this->slot->update(['reserved_count' => 0, 'sold_count' => 10]);

    $payment = Payment::factory()->forBooking($this->booking)->create([
        'status' => PaymentStatus::PENDING,
    ]);

    $this->fake->forceStatus($payment->provider_order_id, webhookPayload($payment));

    $this->postJson(route('webhooks.midtrans'), webhookPayload($payment))->assertOk();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::PAID)
        ->and($payment->needs_review)->toBeTrue()
        // Booking tetap expired; uang tidak otomatis menjadi tiket.
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::EXPIRED)
        ->and(Ticket::query()->count())->toBe(0);
});

test('a refund voids unused tickets and marks the booking refunded', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    $this->fake->forceStatus($payment->provider_order_id, webhookPayload($payment));
    $this->postJson(route('webhooks.midtrans'), webhookPayload($payment))->assertOk();

    expect(Ticket::query()->count())->toBe(2);

    // Notifikasi refund dari gateway.
    $refund = webhookPayload($payment, [
        'transaction_status' => 'refund',
        'status_code' => '200',
        'transaction_id' => 'txn-refund-1',
    ]);
    $this->fake->forceStatus($payment->provider_order_id, $refund);

    $this->postJson(route('webhooks.midtrans'), $refund)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::REFUNDED)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::REFUNDED)
        ->and(Ticket::query()->where('status', TicketStatus::VOID->value)->count())->toBe(2);
});

test('a late webhook with a lower status is ignored instead of rewinding (BR-12)', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    $settled = webhookPayload($payment);
    $this->fake->forceStatus($payment->provider_order_id, $settled);
    $this->postJson(route('webhooks.midtrans'), $settled)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID);

    // Webhook "pending" yang datang terlambat tidak boleh menurunkan status.
    $stale = webhookPayload($payment, [
        'transaction_status' => 'pending',
        'status_code' => '201',
        'transaction_id' => 'txn-late-1',
    ]);
    $this->fake->forceStatus($payment->provider_order_id, $stale);

    $this->postJson(route('webhooks.midtrans'), $stale)
        ->assertOk()
        ->assertJsonPath('result', PaymentEventResult::IGNORED_DUPLICATE->value);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED);
});

test('a card capture with fraud challenge is not treated as paid', function (): void {
    $this->actingAs($this->user)->post(route('bookings.pay', $this->booking->uuid));
    $payment = Payment::query()->firstOrFail();

    $challenged = webhookPayload($payment, [
        'transaction_status' => 'capture',
        'fraud_status' => 'challenge',
    ]);
    $this->fake->forceStatus($payment->provider_order_id, $challenged);

    $this->postJson(route('webhooks.midtrans'), $challenged)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::PENDING)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT)
        ->and(Ticket::query()->count())->toBe(0);
});

test('an unknown order is recorded but does not touch any booking', function (): void {
    $payload = [
        'order_id' => 'MUS-UNKNOWN-9',
        'status_code' => '200',
        'gross_amount' => '10000.00',
        'transaction_status' => 'settlement',
        'transaction_id' => 'txn-unknown',
    ];

    $this->postJson(route('webhooks.midtrans'), $payload)
        ->assertOk()
        ->assertJsonPath('result', PaymentEventResult::UNKNOWN_ORDER->value);

    expect(PaymentEvent::query()->whereNull('payment_id')->count())->toBe(1)
        ->and(Ticket::query()->count())->toBe(0);
});

test('the webhook endpoint is reachable without a session or CSRF token', function (): void {
    $payment = Payment::factory()->forBooking($this->booking)->create();
    $this->fake->forceStatus($payment->provider_order_id, webhookPayload($payment));

    // Tanpa `actingAs` dan tanpa header CSRF.
    $this->postJson(route('webhooks.midtrans'), webhookPayload($payment))->assertOk();
});

test('a malformed webhook payload is rejected with 400', function (): void {
    $this->postJson(route('webhooks.midtrans'), ['foo' => 'bar'])->assertStatus(400);
});

test('the reconcile command confirms a payment whose webhook never arrived', function (): void {
    $payment = Payment::factory()->forBooking($this->booking)->create([
        'status' => PaymentStatus::PENDING,
        'created_at' => now()->subMinutes(20),
    ]);

    $this->fake->forceStatus($payment->provider_order_id, webhookPayload($payment));

    // Rekonsiliasi hanya berjalan bila gateway dianggap live; fake tidak live,
    // jadi perintah memakai klien asli lewat instance yang sama.
    $this->app->instance(MidtransClient::class, new class($this->fake) implements MidtransClient
    {
        public function __construct(private FakeMidtransClient $fake) {}

        public function createSnapTransaction(array $payload): array
        {
            return $this->fake->createSnapTransaction($payload);
        }

        public function fetchTransactionStatus(string $orderId): array
        {
            return $this->fake->fetchTransactionStatus($orderId);
        }

        public function verifySignature(array $payload): bool
        {
            return $this->fake->verifySignature($payload);
        }

        public function isLive(): bool
        {
            return true;
        }
    });

    $this->artisan('payments:reconcile', ['--minutes' => 1])->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED);
});
