<?php

namespace Database\Factories;

use App\Enums\MentorshipSessionStatus;
use App\Enums\MentorshipSessionType;
use App\Models\MentorshipProgram;
use App\Models\MentorshipSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorshipSession>
 */
class MentorshipSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => MentorshipProgram::factory(),
            'scheduled_at' => now()->addDays(3),
            'duration_minutes' => 30,
            'session_type' => MentorshipSessionType::Technical,
            'status' => MentorshipSessionStatus::Scheduled,
            'meeting_link' => fake()->url(),
        ];
    }
}
