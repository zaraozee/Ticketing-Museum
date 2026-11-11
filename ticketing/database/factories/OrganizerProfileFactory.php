<?php

namespace Database\Factories;

use App\Enums\OrganizerStatus;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizerProfile>
 */
class OrganizerProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_name' => fake()->company(),
            'contact_phone' => fake()->phoneNumber(),
            'legal_doc_path' => null,
            'status' => OrganizerStatus::PENDING,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizerStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }
}
