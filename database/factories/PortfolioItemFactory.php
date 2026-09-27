<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\PortfolioItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioItem>
 */
class PortfolioItemFactory extends Factory
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
            'title' => fake()->sentence(4),
            'role' => fake()->jobTitle(),
            'description' => fake()->paragraph(),
            'outcome' => fake()->sentence(),
            'duration_days' => fake()->numberBetween(3, 60),
            'is_visible' => true,
        ];
    }
}
