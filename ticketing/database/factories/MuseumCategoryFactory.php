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
        /*
         * Nama kategori harus unik per database, tetapi tidak boleh memakai
         * `fake()->unique()` pada daftar delapan elemen: generator unik Faker
         * hidup selama satu proses, sehingga tes yang membuat lebih dari
         * delapan museum akan gagal "Maximum retries reached". Sufiks acak
         * memberi ruang yang jauh lebih besar tanpa menyimpan state.
         */
        $name = fake()->randomElement([
            'Sejarah', 'Seni', 'Budaya', 'Sains', 'Arkeologi', 'Etnografi', 'Maritim', 'Geologi',
        ]).' '.Str::upper(Str::random(6));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
