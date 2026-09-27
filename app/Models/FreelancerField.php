<?php

namespace App\Models;

use App\Enums\ExperienceLevel;
use App\Enums\FreelancerFieldStatus;
use Database\Factories\FreelancerFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A top-level field a freelancer works in. Only active fields allow sending proposals.
 */
#[Fillable(['category_id', 'claimed_level', 'is_primary', 'exam_required', 'exam_fee_required', 'status', 'verified_at'])]
class FreelancerField extends Model
{
    /** @use HasFactory<FreelancerFieldFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_level' => ExperienceLevel::class,
            'is_primary' => 'boolean',
            'exam_required' => 'boolean',
            'exam_fee_required' => 'boolean',
            'status' => FreelancerFieldStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<AssessmentAttempt, $this>
     */
    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }
}
