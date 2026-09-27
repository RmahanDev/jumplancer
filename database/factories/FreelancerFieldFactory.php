<?php

namespace Database\Factories;

use App\Enums\ExperienceLevel;
use App\Enums\FreelancerFieldStatus;
use App\Models\Category;
use App\Models\FreelancerField;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FreelancerField>
 */
class FreelancerFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'freelancer_id' => User::factory(),
            'category_id' => Category::factory(),
            'claimed_level' => ExperienceLevel::Beginner,
            'is_primary' => true,
            'exam_required' => false,
            'exam_fee_required' => false,
            'status' => FreelancerFieldStatus::Active,
            'verified_at' => now(),
        ];
    }

    /**
     * A field that is waiting for its entry exam.
     */
    public function pendingExam(): static
    {
        return $this->state(fn (array $attributes) => [
            'exam_required' => true,
            'status' => FreelancerFieldStatus::PendingExam,
            'verified_at' => null,
        ]);
    }
}
