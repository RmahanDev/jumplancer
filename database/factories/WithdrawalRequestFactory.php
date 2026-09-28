<?php

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\BankCard;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Only the request row; use WalletLedger::requestWithdrawal() when the wallet must match.
 *
 * @extends Factory<WithdrawalRequest>
 */
class WithdrawalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_card_id' => BankCard::factory(),
            'user_id' => fn (array $attributes) => BankCard::find($attributes['bank_card_id'])->user_id,
            'amount' => 500_000,
            'card_number' => fn (array $attributes) => BankCard::find($attributes['bank_card_id'])->card_number,
            'holder_name' => fn (array $attributes) => BankCard::find($attributes['bank_card_id'])->holder_name,
            'bank_name' => fn (array $attributes) => BankCard::find($attributes['bank_card_id'])->bank_name,
            'status' => WithdrawalStatus::Pending,
        ];
    }
}
