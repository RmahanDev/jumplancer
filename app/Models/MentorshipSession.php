<?php

namespace App\Models;

use App\Enums\MentorshipSessionStatus;
use App\Enums\MentorshipSessionType;
use Database\Factories\MentorshipSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single meeting inside a mentorship program.
 */
#[Fillable(['scheduled_at', 'duration_minutes', 'session_type', 'status', 'meeting_link', 'mentor_notes', 'mentee_rating'])]
class MentorshipSession extends Model
{
    /** @use HasFactory<MentorshipSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'session_type' => MentorshipSessionType::class,
            'status' => MentorshipSessionStatus::class,
            'mentee_rating' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MentorshipProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(MentorshipProgram::class, 'program_id');
    }
}
