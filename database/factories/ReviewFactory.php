<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'reviewer_id' => fn (array $attributes) => Contract::find($attributes['contract_id'])->employer_id,
            'reviewee_id' => fn (array $attributes) => Contract::find($attributes['contract_id'])->freelancer_id,
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
        ];
    }
}
