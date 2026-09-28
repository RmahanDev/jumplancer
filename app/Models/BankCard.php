<?php

namespace App\Models;

use App\Support\BankCard as CardNumber;
use Database\Factories\BankCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A debit card in the user's own name, registered with their mobile number. Payouts go here.
 */
#[Fillable(['card_number', 'holder_name', 'phone', 'bank_name'])]
class BankCard extends Model
{
    /** @use HasFactory<BankCardFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WithdrawalRequest, $this>
     */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function maskedNumber(): string
    {
        return CardNumber::mask($this->card_number);
    }
}
