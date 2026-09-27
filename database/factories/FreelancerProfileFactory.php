<?php

namespace Database\Factories;

use App\Enums\Availability;
use App\Enums\ExperienceLevel;
use App\Models\FreelancerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FreelancerProfile>
 */
class FreelancerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'headline' => fake()->jobTitle(),
            'level' => ExperienceLevel::Beginner,
            'readiness_score' => fake()->numberBetween(0, 100),
            'hourly_rate' => fake()->numberBetween(1, 20) * 100_000,
            'availability' => Availability::Available,
            'onboarding_completed' => false,
            'free_mentorships_used' => 0,
        ];
    }
}
