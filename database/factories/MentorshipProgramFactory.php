<?php

namespace Database\Factories;

use App\Enums\MentorshipProgramStatus;
use App\Enums\MentorshipTrack;
use App\Models\MentorshipProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorshipProgram>
 */
class MentorshipProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentor_id' => User::factory(),
            'mentee_id' => User::factory(),
            'track' => MentorshipTrack::Freelancer,
            'goal' => fake()->sentence(),
            'status' => MentorshipProgramStatus::Active,
            'is_free_mentorship' => false,
            'price' => 500_000,
            'started_at' => now(),
        ];
    }
}
