<?php

namespace Database\Factories;

use App\Enums\ExamPaymentStatus;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentAttempt>
 */
class AssessmentAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'user_id' => User::factory(),
            'score' => fake()->numberBetween(0, 100),
            'passed' => false,
            'fee_amount' => 0,
            'payment_status' => ExamPaymentStatus::Free,
            'started_at' => now(),
        ];
    }
}
