<?php

namespace Database\Factories;

use App\Models\Museum;
use App\Models\SlotTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlotTemplate>
 */
class SlotTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'museum_id' => Museum::factory(),
            'name' => 'Sesi '.fake()->unique()->randomElement(['Pagi', 'Siang', 'Sore']),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'capacity' => 50,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function capacity(int $capacity): static
    {
        return $this->state(fn (): array => ['capacity' => $capacity]);
    }

    /**
     * Template untuk museum `booking_mode = daily` (satu slot per hari).
     */
    public function daily(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Sepanjang Hari',
            'start_time' => null,
            'end_time' => null,
        ]);
    }

    public function window(string $start, string $end): static
    {
        return $this->state(fn (): array => [
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
