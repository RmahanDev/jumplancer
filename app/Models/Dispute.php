<?php

namespace App\Models;

use App\Enums\DisputeOutcome;
use App\Enums\DisputeStatus;
use Database\Factories\DisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conflict between employer and freelancer, handled by admins.
 */
#[Fillable(['contract_id', 'raised_by', 'reason', 'status', 'outcome', 'resolved_by', 'resolution_note', 'resolved_at'])]
class Dispute extends Model
{
    /** @use HasFactory<DisputeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'outcome' => DisputeOutcome::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * User who raised the dispute.
     *
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    /**
     * Admin who resolved the dispute.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
