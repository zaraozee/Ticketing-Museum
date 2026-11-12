<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Museum;
use App\Models\User;
use App\Models\VisitSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'museum_id' => Museum::factory(),
            'visit_slot_id' => VisitSlot::factory(),
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_amount' => 50000,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => '08123456789',
            'museum_name_snapshot' => fake()->company(),
            'visit_date_snapshot' => now()->addDays(7)->toDateString(),
            'slot_label_snapshot' => '09:00 - 11:00',
            'expires_at' => now()->addMinutes(15),
        ];
    }

    /**
     * Booking yang sudah terkonfirmasi (tiket seharusnya sudah terbit).
     */
    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => BookingStatus::CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => BookingStatus::EXPIRED,
            'expired_at' => now(),
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => BookingStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Booking yang batas bayarnya sudah lewat tetapi statusnya belum diubah.
     */
    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function forSlot(VisitSlot $slot): static
    {
        return $this->state(fn (): array => [
            'museum_id' => $slot->museum_id,
            'visit_slot_id' => $slot->id,
            'visit_date_snapshot' => $slot->visit_date->toDateString(),
            'slot_label_snapshot' => $slot->label(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    public function total(int $amount): static
    {
        return $this->state(fn (): array => ['total_amount' => $amount]);
    }
}
