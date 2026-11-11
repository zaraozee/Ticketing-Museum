<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Toilet', 'Musala', 'Parkir', 'Kafetaria', 'Toko Suvenir', 'Lift',
                'Ruang Laktasi', 'Akses Difabel', 'Pemandu Wisata', 'WiFi',
            ]).' '.fake()->unique()->numerify('#'),
            'icon' => fake()->randomElement(['toilet', 'parking', 'cafe', 'shop', 'accessibility']),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
