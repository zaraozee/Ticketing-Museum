<?php

namespace Database\Factories;

use App\Models\Museum;
use App\Models\VisitSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitSlot>
 */
class VisitSlotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'museum_id' => Museum::factory(),
            'visit_date' => now()->addDays(7)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'capacity' => 50,
            'reserved_count' => 0,
            'sold_count' => 0,
            'status' => 'open',
        ];
    }

    public function capacity(int $capacity): static
    {
        return $this->state(fn (): array => ['capacity' => $capacity]);
    }

    public function reserved(int $count): static
    {
        return $this->state(fn (): array => ['reserved_count' => $count]);
    }

    public function sold(int $count): static
    {
        return $this->state(fn (): array => ['sold_count' => $count]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (): array => ['visit_date' => $date]);
    }

    public function daily(): static
    {
        return $this->state(fn (): array => [
            'start_time' => null,
            'end_time' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => 'closed']);
    }
}
