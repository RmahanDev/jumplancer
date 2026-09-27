<?php

namespace App\Models;

use App\Enums\ExamPaymentStatus;
use Database\Factories\AssessmentAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One try at an assessment. Extra-field exams must be paid before they start.
 */
#[Fillable(['assessment_id', 'freelancer_field_id', 'score', 'passed', 'fee_amount', 'payment_status', 'started_at', 'finished_at'])]
class AssessmentAttempt extends Model
{
    /** @use HasFactory<AssessmentAttemptFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'passed' => 'boolean',
            'fee_amount' => 'integer',
            'payment_status' => ExamPaymentStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FreelancerField, $this>
     */
    public function freelancerField(): BelongsTo
    {
        return $this->belongsTo(FreelancerField::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
