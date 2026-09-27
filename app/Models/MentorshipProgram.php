<?php

namespace App\Models;

use App\Enums\MentorshipProgramStatus;
use App\Enums\MentorshipTrack;
use Database\Factories\MentorshipProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A mentor-mentee relationship created once a ticket is assigned.
 */
#[Fillable(['ticket_id', 'mentor_id', 'mentee_id', 'track', 'goal', 'status', 'is_free_mentorship', 'price', 'started_at', 'ended_at'])]
class MentorshipProgram extends Model
{
    /** @use HasFactory<MentorshipProgramFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'track' => MentorshipTrack::class,
            'status' => MentorshipProgramStatus::class,
            'is_free_mentorship' => 'boolean',
            'price' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    /**
     * @return HasMany<MentorshipSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(MentorshipSession::class, 'program_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
