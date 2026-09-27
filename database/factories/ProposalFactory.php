<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'freelancer_id' => User::factory(),
            'cover_letter' => fake()->paragraph(),
            'proposed_price' => 3_000_000,
            'delivery_days' => fake()->numberBetween(3, 30),
            'status' => ProposalStatus::Pending,
        ];
    }
}
