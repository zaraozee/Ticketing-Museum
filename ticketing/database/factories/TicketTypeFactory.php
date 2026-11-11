<?php

namespace Database\Factories;

use App\Models\Museum;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomElement([10000, 15000, 25000, 35000, 50000]);

        return [
            'museum_id' => Museum::factory(),
            'name' => 'Tiket '.fake()->unique()->randomElement(['Dewasa', 'Pelajar', 'Anak', 'Mahasiswa', 'Lansia']),
            'description' => null,
            'price' => $price,
            'min_age' => null,
            'max_age' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function priced(int $price): static
    {
        return $this->state(fn (): array => ['price' => $price]);
    }
}
