<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => TransactionType::Deposit,
            'amount' => 1_000_000,
            'gateway' => 'zarinpal',
            'gateway_ref' => fake()->numerify('A##########'),
            'status' => TransactionStatus::Succeeded,
            'description' => fake()->sentence(),
        ];
    }
}
