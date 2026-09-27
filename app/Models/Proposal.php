<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A freelancer's bid on a project. One proposal per freelancer per project.
 */
#[Fillable(['cover_letter', 'proposed_price', 'delivery_days', 'status', 'mentor_reviewed_by', 'mentor_feedback'])]
class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_price' => 'integer',
            'delivery_days' => 'integer',
            'status' => ProposalStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    /**
     * Mentor who reviewed the proposal before it was sent.
     *
     * @return BelongsTo<User, $this>
     */
    public function mentorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_reviewed_by');
    }

    /**
     * @return HasOne<Contract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /**
     * @return MorphMany<Violation, $this>
     */
    public function violations(): MorphMany
    {
        return $this->morphMany(Violation::class, 'violatable');
    }
}
