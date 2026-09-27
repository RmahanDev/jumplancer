<?php

namespace App\Models;

use App\Enums\ViolationAction;
use App\Enums\ViolationSource;
use App\Enums\ViolationType;
use Database\Factories\ViolationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Logged off-platform-contact attempt (phone, email, link) on a message, proposal, project or portfolio.
 */
#[Fillable(['user_id', 'violation_type', 'detected_content', 'detected_by', 'action_taken'])]
class Violation extends Model
{
    /** @use HasFactory<ViolationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'violation_type' => ViolationType::class,
            'detected_by' => ViolationSource::class,
            'action_taken' => ViolationAction::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The message, proposal, project, portfolio item or media where it happened.
     *
     * @return MorphTo<Model, $this>
     */
    public function violatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Staff member who confirmed or reversed it.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
