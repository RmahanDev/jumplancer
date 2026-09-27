<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money ledger row. Never edit amounts - add a new row instead.
 */
#[Fillable(['contract_id', 'milestone_id', 'subscription_id', 'assessment_attempt_id', 'mentorship_program_id', 'type', 'amount', 'gateway', 'gateway_ref', 'status', 'description'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'integer',
            'status' => TransactionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return BelongsTo<EmployerSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(EmployerSubscription::class, 'subscription_id');
    }

    /**
     * @return BelongsTo<AssessmentAttempt, $this>
     */
    public function assessmentAttempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class);
    }

    /**
     * @return BelongsTo<MentorshipProgram, $this>
     */
    public function mentorshipProgram(): BelongsTo
    {
        return $this->belongsTo(MentorshipProgram::class);
    }
}
