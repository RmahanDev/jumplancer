<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One multiple-choice question of an exam, with an optional hint shown to the freelancer.
 */
#[Fillable(['body', 'hint', 'sort_order'])]
class AssessmentQuestion extends Model
{
    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return HasMany<AssessmentOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(AssessmentOption::class, 'question_id')->orderBy('sort_order')->orderBy('id');
    }
}
