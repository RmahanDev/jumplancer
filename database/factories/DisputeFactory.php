<?php

namespace Database\Factories;

use App\Enums\DisputeStatus;
use App\Models\Contract;
use App\Models\Dispute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
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
            'raised_by' => fn (array $attributes) => Contract::find($attributes['contract_id'])->employer_id,
            'reason' => fake()->paragraph(),
            'status' => DisputeStatus::Open,
        ];
    }
}
