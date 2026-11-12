<?php

use App\Enums\ScanResult;
use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;
use App\Services\Gate\ScanService;
use App\Services\Ticket\TicketTokenSigner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    // Bekukan waktu agar jendela scan tidak bergantung pada jam dinding saat
    // suite dijalankan (mis. gagal bila dijalankan pukul 08:00 WIB).
    $this->travelTo(Carbon::parse('2026-03-10 12:00:00', 'Asia/Jakarta'));

    config(['ticketing.ticket_signing_keys' => json_encode(['k1' => 'scan-test-key'])]);
    $this->signer = app(TicketTokenSigner::class);
    $this->scanService = app(ScanService::class);
});

afterEach(function () {
    $this->travelBack();
});

function createBookingWithTickets(Museum $museum, User $user): Booking
{
    // Set museum ke timed_slot mode agar tidak butuh operating_hours
    $museum->update(['booking_mode' => 'timed_slot']);

    $slot = VisitSlot::factory()->for($museum)->create([
        'visit_date' => now()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);
    $ticketType = TicketType::factory()->for($museum)->create();

    $booking = Booking::factory()
        ->for($user)
        ->for($museum)
        ->for($slot, 'visitSlot')
        ->confirmed()
        ->create();

    $item = BookingItem::factory()
        ->for($booking)
        ->for($ticketType)
        ->create(['quantity' => 2]);

    // Create tickets manually with all required fields
    for ($i = 0; $i < $item->quantity; $i++) {
        $uuid = (string) Str::uuid();
        Ticket::create([
            'booking_id' => $booking->id,
            'booking_item_id' => $item->id,
            'museum_id' => $museum->id,
            'visit_slot_id' => $slot->id,
            'uuid' => $uuid,
            'ticket_code' => Ticket::generateTicketCode(),
            'token_hash' => hash('sha256', 'test-token-'.$uuid),
            'status' => TicketStatus::ACTIVE,
            'sequence' => $i + 1,
        ]);
    }

    return $booking->fresh(['tickets']);
}

test('scan valid mengubah tiket menjadi used', function () {
    $user = User::factory()->create();
    $museum = Museum::factory()->published()->create();
    $booking = createBookingWithTickets($museum, $user);

    $ticket = $booking->tickets()->first();
    expect($ticket->status)->toBe(TicketStatus::ACTIVE);

    $qrToken = $this->signer->sign($ticket->uuid);
    $scannedBy = User::factory()->create();

    $result = $this->scanService->scan(
        museumId: (string) $museum->id,
        qrPayload: $qrToken,
        ticketCode: null,
        clientScanUuid: Str::uuid()->toString(),
        scannedBy: $scannedBy->id
    );

    expect($result['result'])->toBe(ScanResult::VALID)
        ->and($result['ticket']->status)->toBe(TicketStatus::USED)
        ->and($result['ticket']->used_at)->not->toBeNull()
        ->and($result['scan']->result)->toBe(ScanResult::VALID);
});

test('scan tiket yang sudah used mengembalikan already_used', function () {
    $user = User::factory()->create();
    $museum = Museum::factory()->published()->create();
    $booking = createBookingWithTickets($museum, $user);

    $ticket = $booking->tickets()->first();
    $ticket->update(['status' => TicketStatus::USED, 'used_at' => now()->subMinutes(5)]);

    $qrToken = $this->signer->sign($ticket->uuid);
    $scannedBy = User::factory()->create();

    $result = $this->scanService->scan(
        museumId: (string) $museum->id,
        qrPayload: $qrToken,
        ticketCode: null,
        clientScanUuid: Str::uuid()->toString(),
        scannedBy: $scannedBy->id
    );

    expect($result['result'])->toBe(ScanResult::ALREADY_USED);
});

test('scan tiket di museum lain mengembalikan wrong_museum', function () {
    $user = User::factory()->create();
    $museumA = Museum::factory()->published()->create();
    $museumB = Museum::factory()->published()->create();
    $booking = createBookingWithTickets($museumA, $user);

    $ticket = $booking->tickets()->first();
    $qrToken = $this->signer->sign($ticket->uuid);
    $scannedBy = User::factory()->create();

    $result = $this->scanService->scan(
        museumId: (string) $museumB->id,
        qrPayload: $qrToken,
        ticketCode: null,
        clientScanUuid: Str::uuid()->toString(),
        scannedBy: $scannedBy->id
    );

    expect($result['result'])->toBe(ScanResult::WRONG_MUSEUM)
        ->and($result['ticket']->status)->toBe(TicketStatus::ACTIVE); // Tidak berubah
});

test('scan token invalid mengembalikan invalid', function () {
    $museum = Museum::factory()->published()->create();
    $scannedBy = User::factory()->create();

    $result = $this->scanService->scan(
        museumId: (string) $museum->id,
        qrPayload: 'MT1.k1.fakeuuid.fakesig',
        ticketCode: null,
        clientScanUuid: Str::uuid()->toString(),
        scannedBy: $scannedBy->id
    );

    expect($result['result'])->toBe(ScanResult::INVALID)
        ->and($result['ticket'])->toBeNull();
});

test('scan tiket voided mengembalikan voided', function () {
    $user = User::factory()->create();
    $museum = Museum::factory()->published()->create();
    $booking = createBookingWithTickets($museum, $user);

    $ticket = $booking->tickets()->first();
    $ticket->update(['status' => TicketStatus::VOID]);

    $qrToken = $this->signer->sign($ticket->uuid);
    $scannedBy = User::factory()->create();

    $result = $this->scanService->scan(
        museumId: (string) $museum->id,
        qrPayload: $qrToken,
        ticketCode: null,
        clientScanUuid: Str::uuid()->toString(),
        scannedBy: $scannedBy->id
    );

    expect($result['result'])->toBe(ScanResult::VOIDED);
});

test('retry scan dengan client_scan_uuid sama mengembalikan hasil awal', function () {
    $user = User::factory()->create();
    $museum = Museum::factory()->published()->create();
    $booking = createBookingWithTickets($museum, $user);

    $ticket = $booking->tickets()->first();
    $qrToken = $this->signer->sign($ticket->uuid);
    $scannedBy = User::factory()->create();
    $clientUuid = Str::uuid()->toString();

    // Scan pertama
    $result1 = $this->scanService->scan(
        museumId: (string) $museum->id,
        qrPayload: $qrToken,
        ticketCode: null,
        clientScanUuid: $clientUuid,
        scannedBy: $scannedBy->id
    );

    // Retry dengan UUID sama
    $result2 = $this->scanService->scan(
        museumId: (string) $museum->id,
        qrPayload: $qrToken,
        ticketCode: null,
        clientScanUuid: $clientUuid,
        scannedBy: $scannedBy->id
    );

    expect($result1['result'])->toBe(ScanResult::VALID)
        ->and($result2['result'])->toBe(ScanResult::VALID)
        ->and($result2['scan']->id)->toBe($result1['scan']->id)
        ->and($result2['message'])->toContain('retry');
});

test('scan bersamaan hanya satu yang berhasil', function () {
    $user = User::factory()->create();
    $museum = Museum::factory()->published()->create();
    $booking = createBookingWithTickets($museum, $user);

    $ticket = $booking->tickets()->first();
    $qrToken = $this->signer->sign($ticket->uuid);
    $scannedBy = User::factory()->create();

    // Simulasi race condition: dua scan hampir bersamaan
    $result1 = $this->scanService->scan(
        museumId: (string) $museum->id,
        qrPayload: $qrToken,
        ticketCode: null,
        clientScanUuid: Str::uuid()->toString(),
        scannedBy: $scannedBy->id
    );

    $result2 = $this->scanService->scan(
        museumId: (string) $museum->id,
        qrPayload: $qrToken,
        ticketCode: null,
        clientScanUuid: Str::uuid()->toString(),
        scannedBy: $scannedBy->id
    );

    // Satu valid, satunya already_used
    $results = [$result1['result'], $result2['result']];
    expect($results)->toContain(ScanResult::VALID)
        ->and($results)->toContain(ScanResult::ALREADY_USED);
});
