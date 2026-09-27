<?php

namespace App\Models;

use App\Enums\MentoringStyle;
use Database\Factories\MentorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mentor-specific data. Mentors guide both freelancers and employers (1:1 with User).
 */
#[Fillable(['expertise_summary', 'years_experience', 'mentoring_style', 'max_mentees', 'is_volunteer'])]
class MentorProfile extends Model
{
    /** @use HasFactory<MentorProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'years_experience' => 'integer',
            'mentoring_style' => MentoringStyle::class,
            'max_mentees' => 'integer',
            'is_verified' => 'boolean',
            'is_volunteer' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
