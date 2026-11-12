<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\Ticket;
use App\Models\VisitSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) str()->uuid();

        return [
            'uuid' => $uuid,
            'ticket_code' => Ticket::generateTicketCode(),
            // Hash token nyata agar kolom unik tidak bertabrakan antar-factory.
            'token_hash' => hash('sha256', 'factory-'.$uuid),
            'booking_id' => Booking::factory(),
            'booking_item_id' => BookingItem::factory(),
            'sequence' => 1,
            'museum_id' => Museum::factory(),
            'visit_slot_id' => VisitSlot::factory(),
            'holder_name' => fake()->name(),
            'status' => TicketStatus::ACTIVE,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::USED,
            'used_at' => now(),
        ]);
    }

    public function void(): static
    {
        return $this->state(fn (): array => ['status' => TicketStatus::VOID]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['status' => TicketStatus::EXPIRED]);
    }

    public function ticketSequence(int $seq): static
    {
        return $this->state(fn (): array => ['sequence' => $seq]);
    }
}
