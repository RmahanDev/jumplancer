<?php

namespace Database\Factories;

use App\Enums\MilestoneStatus;
use App\Models\Contract;
use App\Models\Milestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
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
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'amount' => 1_000_000,
            'due_date' => now()->addWeeks(2),
            'status' => MilestoneStatus::Pending,
            'sort_order' => 0,
        ];
    }
}
