<?php

namespace Database\Factories;

use App\Enums\BudgetType;
use App\Enums\PostingType;
use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employer_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraphs(2, true),
            'budget_type' => BudgetType::Fixed,
            'budget_min' => 2_000_000,
            'budget_max' => 5_000_000,
            'status' => ProjectStatus::Open,
            'is_beginner_friendly' => true,
            'deadline' => now()->addMonth(),
            'published_at' => now(),
            'posting_type' => PostingType::FreeFirst,
        ];
    }

    /**
     * A project that is not published yet.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Draft,
            'published_at' => null,
        ]);
    }
}
