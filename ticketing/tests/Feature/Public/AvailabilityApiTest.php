<?php

use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\MuseumClosedDate;
use App\Models\MuseumOperatingHour;
use App\Models\OrganizerProfile;
use App\Models\TicketType;
use App\Models\VisitSlot;

beforeEach(function (): void {
    $category = MuseumCategory::factory()->create();
    $profile = OrganizerProfile::factory()->approved()->create();

    $this->museum = Museum::factory()
        ->forOrganizer($profile)
        ->published()
        ->create([
            'category_id' => $category->id,
            'booking_mode' => 'timed_slot',
            'max_tickets_per_booking' => 10,
        ]);

    // Buka setiap hari agar status harian ditentukan oleh kuota, bukan jadwal.
    foreach (range(0, 6) as $day) {
        MuseumOperatingHour::create([
            'museum_id' => $this->museum->id,
            'day_of_week' => $day,
            'open_time' => '09:00',
            'close_time' => '17:00',
            'is_closed' => false,
        ]);
    }
});

test('the monthly endpoint returns one entry per day of the month', function (): void {
    $response = $this->getJson("/api/v1/museums/{$this->museum->slug}/availability?month=2026-12")
        ->assertOk();

    $days = $response->json('days');

    expect($days)->toHaveCount(31)
        ->and($days[0]['date'])->toBe('2026-12-01')
        ->and($days[30]['date'])->toBe('2026-12-31');
});

test('availability reflects available, almost full and full statuses', function (): void {
    // 1 Des: sisa banyak -> available
    VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => '2026-12-01',
        'start_time' => '09:00:00',
        'capacity' => 100,
        'sold_count' => 0,
    ]);

    // 2 Des: sisa 10% -> almost_full
    VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => '2026-12-02',
        'start_time' => '09:00:00',
        'capacity' => 100,
        'sold_count' => 90,
    ]);

    // 3 Des: habis -> full
    VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => '2026-12-03',
        'start_time' => '09:00:00',
        'capacity' => 100,
        'sold_count' => 100,
    ]);

    $days = collect($this->getJson("/api/v1/museums/{$this->museum->slug}/availability?month=2026-12")
        ->assertOk()
        ->json('days'))->keyBy('date');

    expect($days['2026-12-01']['status'])->toBe('available')
        ->and($days['2026-12-01']['status_label'])->toBe('Tersedia')
        ->and($days['2026-12-01']['remaining'])->toBe(100)
        ->and($days['2026-12-02']['status'])->toBe('almost_full')
        ->and($days['2026-12-02']['status_label'])->toBe('Hampir penuh')
        ->and($days['2026-12-02']['remaining'])->toBe(10)
        ->and($days['2026-12-03']['status'])->toBe('full')
        ->and($days['2026-12-03']['status_label'])->toBe('Penuh')
        ->and($days['2026-12-03']['remaining'])->toBe(0);
});

test('a closed date is reported as closed', function (): void {
    VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => '2026-12-05',
        'start_time' => '09:00:00',
        'capacity' => 100,
    ]);

    MuseumClosedDate::create([
        'museum_id' => $this->museum->id,
        'closed_on' => '2026-12-05',
        'reason' => 'Libur nasional',
    ]);

    $days = collect($this->getJson("/api/v1/museums/{$this->museum->slug}/availability?month=2026-12")
        ->assertOk()
        ->json('days'))->keyBy('date');

    expect($days['2026-12-05']['status'])->toBe('closed');
});

test('a weekly closed day is reported as closed', function (): void {
    // Tutup setiap Senin; 2026-12-07 adalah hari Senin.
    MuseumOperatingHour::where('museum_id', $this->museum->id)
        ->where('day_of_week', 1)
        ->update(['is_closed' => true]);

    $days = collect($this->getJson("/api/v1/museums/{$this->museum->slug}/availability?month=2026-12")
        ->assertOk()
        ->json('days'))->keyBy('date');

    expect($days['2026-12-07']['status'])->toBe('closed')
        // 8 Des bukan hari tutup, tetapi belum punya slot -> `not_open`,
        // bukan `closed` (agar klien tidak salah menyimpulkan museum tutup).
        ->and($days['2026-12-08']['status'])->toBe('not_open')
        ->and($days['2026-12-08']['status_label'])->toBe('Belum dibuka');
});

test('the slots endpoint returns slots with remaining quota and ticket types', function (): void {
    VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => '2026-12-10',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'capacity' => 50,
        'reserved_count' => 5,
        'sold_count' => 10,
    ]);

    VisitSlot::factory()->create([
        'museum_id' => $this->museum->id,
        'visit_date' => '2026-12-10',
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'capacity' => 50,
        'sold_count' => 50,
    ]);

    TicketType::factory()->priced(25000)->create([
        'museum_id' => $this->museum->id,
        'name' => 'Dewasa',
    ]);

    TicketType::factory()->inactive()->create([
        'museum_id' => $this->museum->id,
        'name' => 'Tidak Aktif',
    ]);

    $payload = $this->getJson("/api/v1/museums/{$this->museum->slug}/slots?date=2026-12-10")
        ->assertOk()
        ->json();

    expect($payload['slots'])->toHaveCount(2)
        ->and($payload['slots'][0]['label'])->toBe('09:00 - 11:00')
        ->and($payload['slots'][0]['remaining'])->toBe(35)
        ->and($payload['slots'][0]['bookable'])->toBeTrue()
        ->and($payload['slots'][1]['remaining'])->toBe(0)
        ->and($payload['slots'][1]['status'])->toBe('full')
        ->and($payload['slots'][1]['bookable'])->toBeFalse()
        // Hanya jenis tiket aktif yang ditampilkan.
        ->and($payload['ticket_types'])->toHaveCount(1)
        ->and($payload['ticket_types'][0]['name'])->toBe('Dewasa')
        ->and($payload['ticket_types'][0]['price_label'])->toBe('Rp 25.000')
        ->and($payload['max_tickets_per_booking'])->toBe(10);
});

test('a draft museum is not served by the availability endpoints', function (): void {
    $this->museum->update(['status' => 'draft']);

    $this->getJson("/api/v1/museums/{$this->museum->slug}/availability?month=2026-12")
        ->assertNotFound();

    $this->getJson("/api/v1/museums/{$this->museum->slug}/slots?date=2026-12-10")
        ->assertNotFound();
});

test('a suspended museum is not served by the availability endpoints', function (): void {
    $this->museum->update(['status' => 'suspended']);

    $this->getJson("/api/v1/museums/{$this->museum->slug}/availability?month=2026-12")
        ->assertNotFound();
});

test('the slots endpoint requires a valid date', function (): void {
    $this->getJson("/api/v1/museums/{$this->museum->slug}/slots")
        ->assertStatus(422);

    $this->getJson("/api/v1/museums/{$this->museum->slug}/slots?date=bukan-tanggal")
        ->assertStatus(422);
});

test('an invalid month falls back to the current month', function (): void {
    $payload = $this->getJson("/api/v1/museums/{$this->museum->slug}/availability?month=abc")
        ->assertOk()
        ->json();

    expect($payload['month'])->toBe(now()->format('Y-m'));
});
