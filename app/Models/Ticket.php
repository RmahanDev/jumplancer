<?php

namespace App\Models;

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Every mentoring request starts as a ticket.
 */
#[Fillable(['ticket_type', 'channel', 'phone_number', 'subject', 'message', 'status', 'assigned_mentor_id', 'closed_at'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_type' => TicketType::class,
            'channel' => TicketChannel::class,
            'status' => TicketStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedMentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_mentor_id');
    }

    /**
     * @return HasMany<MentorshipProgram, $this>
     */
    public function mentorshipPrograms(): HasMany
    {
        return $this->hasMany(MentorshipProgram::class);
    }
}
