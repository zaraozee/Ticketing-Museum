<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\OrganizerProfile;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // User pengunjung
        $user = User::firstOrCreate(
            ['email' => 'user@test.com'],
            [
                'name' => 'Demo User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
        if (! $user->hasRole('user')) {
            $user->assignRole('user');
        }

        // Organizer
        $organizer = User::firstOrCreate(
            ['email' => 'organizer@test.com'],
            [
                'name' => 'Demo Organizer',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
        if (! $organizer->hasRole('organizer')) {
            $organizer->assignRole('organizer');
        }

        $profile = $organizer->organizerProfile ?? OrganizerProfile::factory()->for($organizer)->create([
            'organization_name' => 'Yayasan Museum Demo',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Museum
        $museum = Museum::factory()->for($profile, 'organizerProfile')->published()->create([
            'name' => 'Museum Sejarah Nasional',
            'booking_mode' => 'timed_slot',
            'description' => 'Museum dengan koleksi sejarah Indonesia yang lengkap dan menarik.',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
        ]);

        // Jam operasional
        foreach ([1, 2, 3, 4, 5] as $day) {
            $museum->operatingHours()->create([
                'day_of_week' => $day,
                'open_time' => '09:00:00',
                'close_time' => '17:00:00',
                'is_closed' => false,
            ]);
        }

        // Jenis tiket
        $ticketAdult = TicketType::factory()->for($museum)->create([
            'name' => 'Dewasa',
            'price' => 50000,
            'is_active' => true,
        ]);

        $ticketChild = TicketType::factory()->for($museum)->create([
            'name' => 'Anak-anak',
            'price' => 25000,
            'is_active' => true,
        ]);

        // Slot besok
        $tomorrow = now()->addDay();
        $slot = VisitSlot::factory()->for($museum)->create([
            'visit_date' => $tomorrow->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'capacity' => 50,
            'reserved_count' => 0,
            'sold_count' => 2,
        ]);

        // Booking confirmed dengan 2 tiket
        $booking = Booking::factory()->for($user)->for($museum)->for($slot, 'visitSlot')->create([
            'status' => BookingStatus::CONFIRMED,
            'booking_code' => 'BK'.strtoupper(Str::random(8)),
            'total_amount' => 100000,
            'contact_name' => 'Demo User',
            'contact_email' => 'user@test.com',
            'contact_phone' => '081234567890',
        ]);

        $item = BookingItem::factory()->for($booking)->for($ticketAdult)->create([
            'quantity' => 2,
            'unit_price' => 50000,
            'ticket_name_snapshot' => 'Dewasa',
        ]);

        Payment::factory()->for($booking)->create([
            'status' => PaymentStatus::PAID,
            'gross_amount' => 100000,
            'paid_at' => now(),
        ]);

        // 2 tiket aktif
        for ($i = 1; $i <= 2; $i++) {
            Ticket::create([
                'uuid' => (string) Str::uuid(),
                'ticket_code' => $this->generateTicketCode(),
                'token_hash' => hash('sha256', Str::random(32)),
                'booking_id' => $booking->id,
                'booking_item_id' => $item->id,
                'museum_id' => $museum->id,
                'visit_slot_id' => $slot->id,
                'status' => TicketStatus::ACTIVE,
                'sequence' => $i,
            ]);
        }

        $this->command->info('Demo data created:');
        $this->command->info('User: user@test.com / password');
        $this->command->info('Organizer: organizer@test.com / password');
        $this->command->info('Admin: admin@example.com / password');
    }

    private function generateTicketCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 10; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
