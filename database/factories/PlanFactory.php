<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' plan',
            'price' => fake()->numberBetween(1, 10) * 500_000,
            'project_quota' => 5,
            'duration_days' => 30,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
