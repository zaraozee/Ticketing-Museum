<?php

namespace Database\Factories;

use App\Enums\MuseumStatus;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\OrganizerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Museum>
 */
class MuseumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Museum '.fake()->unique()->words(2, true);

        return [
            'organizer_profile_id' => OrganizerProfile::factory(),
            'category_id' => MuseumCategory::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'venue_type' => 'museum',
            'description' => fake()->paragraph(),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Jakarta', 'Bandung', 'Yogyakarta', 'Surabaya', 'Semarang']),
            'province' => fake()->randomElement(['DKI Jakarta', 'Jawa Barat', 'DI Yogyakarta', 'Jawa Timur', 'Jawa Tengah']),
            'latitude' => fake()->latitude(-8.5, -6.0),
            'longitude' => fake()->longitude(106.0, 112.0),
            'timezone' => 'Asia/Jakarta',
            'status' => MuseumStatus::DRAFT,
            'booking_mode' => 'daily',
            'max_tickets_per_booking' => 10,
            'entry_early_minutes' => 15,
            'entry_late_minutes' => 30,
            'youtube_video_id' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => MuseumStatus::PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (): array => [
            'status' => MuseumStatus::PENDING_REVIEW,
            'submitted_at' => now(),
        ]);
    }

    public function forOrganizer(OrganizerProfile $profile): static
    {
        return $this->state(fn (): array => [
            'organizer_profile_id' => $profile->id,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => MuseumStatus::SUSPENDED,
            'published_at' => now()->subMonth(),
        ]);
    }
}
