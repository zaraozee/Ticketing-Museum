<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'provider' => 'midtrans',
            'provider_order_id' => 'MUS-'.fake()->unique()->numerify('######').'-1',
            'gross_amount' => 100000,
            'status' => PaymentStatus::PENDING,
            'expires_at' => now()->addMinutes(13),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
            'provider_transaction_id' => 'txn-'.fake()->unique()->numerify('########'),
            'payment_type' => 'qris',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => PaymentStatus::FAILED]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['status' => PaymentStatus::EXPIRED]);
    }

    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (): array => [
            'booking_id' => $booking->id,
            'provider_order_id' => $booking->booking_code.'-1',
            'gross_amount' => $booking->total_amount,
        ]);
    }

    public function amount(int $amount): static
    {
        return $this->state(fn (): array => ['gross_amount' => $amount]);
    }

    public function needsReview(): static
    {
        return $this->state(fn (): array => [
            'needs_review' => true,
            'review_note' => 'Perlu tinjauan manual.',
        ]);
    }
}
