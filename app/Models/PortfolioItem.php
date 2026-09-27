<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use Database\Factories\PortfolioItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Structured, optional portfolio case study that replaces raw file/link uploads.
 */
#[Fillable(['category_id', 'title', 'role', 'description', 'outcome', 'duration_days', 'is_visible'])]
class PortfolioItem extends Model
{
    /** @use HasFactory<PortfolioItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'is_visible' => 'boolean',
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
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /**
     * @return HasMany<PortfolioMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(PortfolioMedia::class);
    }

    /**
     * Only proof files a reviewer approved; the only media employers may see.
     *
     * @return HasMany<PortfolioMedia, $this>
     */
    public function approvedMedia(): HasMany
    {
        return $this->hasMany(PortfolioMedia::class)->where('status', ModerationStatus::Approved);
    }

    /**
     * @return MorphMany<Violation, $this>
     */
    public function violations(): MorphMany
    {
        return $this->morphMany(Violation::class, 'violatable');
    }

    /**
     * Case studies the freelancer chose to show to employers.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_visible', true);
    }
}
