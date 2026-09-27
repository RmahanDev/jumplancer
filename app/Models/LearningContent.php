<?php

namespace App\Models;

use App\Enums\ContentAudience;
use App\Enums\ContentPurpose;
use App\Enums\LearningContentType;
use Database\Factories\LearningContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Articles, videos, checklists and roadmaps that support mentoring.
 */
#[Fillable(['category_id', 'title', 'content_type', 'audience', 'purpose', 'body', 'media_url', 'is_published', 'published_at'])]
class LearningContent extends Model
{
    /** @use HasFactory<LearningContentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_type' => LearningContentType::class,
            'audience' => ContentAudience::class,
            'purpose' => ContentPurpose::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Content visible to users.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
