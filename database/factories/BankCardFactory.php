<?php

namespace Database\Factories;

use App\Models\BankCard;
use App\Models\User;
use App\Support\BankCard as CardNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankCard>
 */
class BankCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = self::validNumber();

        return [
            'user_id' => User::factory(),
            'card_number' => $number,
            'holder_name' => fn (array $attributes) => User::find($attributes['user_id'])->name,
            'phone' => fn (array $attributes) => User::find($attributes['user_id'])->phone ?? '09120000000',
            'bank_name' => CardNumber::bankName($number),
        ];
    }

    /**
     * A random card number that passes the Luhn check.
     */
    public static function validNumber(string $prefix = '603799'): string
    {
        $digits = $prefix.str_pad((string) fake()->numberBetween(0, 999_999_999), 9, '0', STR_PAD_LEFT);

        for ($check = 0; $check <= 9; $check++) {
            if (CardNumber::isValid($digits.$check)) {
                return $digits.$check;
            }
        }

        return self::validNumber($prefix);
    }
}
