<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'project_id' => fn (array $attributes) => Proposal::find($attributes['proposal_id'])->project_id,
            'employer_id' => fn (array $attributes) => Project::find($attributes['project_id'])->employer_id,
            'freelancer_id' => fn (array $attributes) => Proposal::find($attributes['proposal_id'])->freelancer_id,
            'amount' => fn (array $attributes) => Proposal::find($attributes['proposal_id'])->proposed_price,
            'mentorship_included' => false,
            'is_free_mentorship' => false,
            'fee_percent' => 20,
            'status' => ContractStatus::Active,
            'started_at' => now(),
        ];
    }
}
