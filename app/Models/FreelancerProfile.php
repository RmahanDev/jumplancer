<?php

namespace App\Models;

use App\Enums\Availability;
use App\Enums\ExperienceLevel;
use Database\Factories\FreelancerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Freelancer-specific data for users with the freelancer role (1:1 with User).
 */
#[Fillable(['headline', 'level', 'hourly_rate', 'availability', 'onboarding_completed'])]
class FreelancerProfile extends Model
{
    /** @use HasFactory<FreelancerProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ExperienceLevel::class,
            'readiness_score' => 'integer',
            'hourly_rate' => 'integer',
            'availability' => Availability::class,
            'onboarding_completed' => 'boolean',
            'free_mentorships_used' => 'integer',
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
