<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingItem>
 */
class BookingItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = 50000;
        $quantity = 2;

        return [
            'booking_id' => Booking::factory(),
            'ticket_type_id' => TicketType::factory(),
            'ticket_name_snapshot' => 'Tiket Dewasa',
            'unit_price' => $price,
            'quantity' => $quantity,
            'subtotal' => $price * $quantity,
        ];
    }

    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (): array => ['booking_id' => $booking->id]);
    }

    public function forTicketType(TicketType $ticketType): static
    {
        return $this->state(fn (): array => [
            'ticket_type_id' => $ticketType->id,
            'ticket_name_snapshot' => $ticketType->name,
            'unit_price' => $ticketType->price,
            'subtotal' => $ticketType->price * 2,
        ]);
    }

    public function quantity(int $quantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity' => $quantity,
            'subtotal' => (int) $attributes['unit_price'] * $quantity,
        ]);
    }
}
