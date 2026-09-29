<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Enums\MilestoneStatus;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Agreement created when an employer accepts a proposal.
 *
 * deposit_amount is the good-faith deposit held when hiring (a share of the proposal price);
 * deposit_balance is the part of it not yet spent on milestones. Both stay in the employer's
 * held_balance until milestones use them, the contract ends, or an expert decides a dispute.
 */
#[Fillable(['project_id', 'proposal_id', 'employer_id', 'freelancer_id', 'mentor_id', 'amount', 'deposit_amount', 'deposit_balance', 'mentorship_included', 'is_free_mentorship', 'fee_percent', 'mentor_share_percent', 'status', 'started_at', 'completed_at'])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
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
            'deposit_amount' => 'integer',
            'deposit_balance' => 'integer',
            'mentorship_included' => 'boolean',
            'is_free_mentorship' => 'boolean',
            'fee_percent' => 'float',
            'mentor_share_percent' => 'float',
            'status' => ContractStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort_order');
    }

    /**
     * Money of this contract currently locked in the employer's wallet: the unspent deposit
     * plus milestones that are funded but not yet paid.
     */
    public function heldAmount(): int
    {
        return $this->deposit_balance + (int) $this->milestones()
            ->whereIn('status', [MilestoneStatus::Funded, MilestoneStatus::Submitted, MilestoneStatus::Approved])
            ->sum('amount');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<Dispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * The dispute still waiting for an expert, if any.
     *
     * @return HasOne<Dispute, $this>
     */
    public function openDispute(): HasOne
    {
        return $this->hasOne(Dispute::class)->ofMany(['id' => 'max'], fn ($query) => $query->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview]));
    }

    /**
     * Mentoring ticket opened for this contract (the freelancer asked for a mentor).
     *
     * @return HasOne<Ticket, $this>
     */
    public function mentorshipTicket(): HasOne
    {
        return $this->hasOne(Ticket::class)->latestOfMany();
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
