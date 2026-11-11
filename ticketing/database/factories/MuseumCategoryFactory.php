<?php

namespace Database\Factories;

use App\Models\MuseumCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MuseumCategory>
 */
class MuseumCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Sejarah', 'Seni', 'Budaya', 'Sains', 'Arkeologi', 'Etnografi', 'Maritim', 'Geologi',
        ]).' '.fake()->unique()->numerify('##');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
