<?php

namespace Database\Factories;

use App\Enums\AssessmentScope;
use App\Enums\ExperienceLevel;
use App\Models\Assessment;
use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => AssessmentScope::Skill,
            'skill_id' => Skill::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'pass_score' => 70,
            'time_limit_minutes' => 30,
            'is_active' => true,
        ];
    }

    /**
     * An entry exam for a whole field (top-level category).
     */
    public function field(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => AssessmentScope::Field,
            'skill_id' => null,
            'category_id' => Category::factory(),
            'target_level' => ExperienceLevel::Intermediate,
        ]);
    }
}
