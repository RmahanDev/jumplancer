<?php

namespace Database\Factories;

use App\Enums\MentoringStyle;
use App\Models\MentorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorProfile>
 */
class MentorProfileFactory extends Factory
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
            'expertise_summary' => fake()->paragraph(),
            'years_experience' => fake()->numberBetween(1, 20),
            'mentoring_style' => MentoringStyle::Both,
            'max_mentees' => 5,
            'is_verified' => true,
            'is_volunteer' => true,
        ];
    }
}
