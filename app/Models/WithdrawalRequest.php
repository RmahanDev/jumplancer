<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Database\Factories\WithdrawalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Pay my wallet money to my card". The amount leaves the available balance right away (a pending
 * payout row); staff pay it by bank transfer and record the tracking number and a receipt image,
 * or reject it and the money returns to the wallet. Card details are copied so history never changes.
 */
#[Fillable(['bank_card_id', 'amount', 'card_number', 'holder_name', 'bank_name', 'status'])]
class WithdrawalRequest extends Model
{
    /** @use HasFactory<WithdrawalRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => WithdrawalStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BankCard, $this>
     */
    public function bankCard(): BelongsTo
    {
        return $this->belongsTo(BankCard::class);
    }

    /**
     * The pending (then succeeded or cancelled) payout row in the ledger.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Admin or support agent who paid or rejected the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
