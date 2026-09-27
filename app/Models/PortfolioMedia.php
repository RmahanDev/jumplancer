<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use App\Enums\PortfolioMediaType;
use Database\Factories\PortfolioMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Image/PDF proof for a case study. Hidden until a reviewer checks it for contact info.
 */
#[Fillable(['file_path', 'file_type', 'original_filename'])]
class PortfolioMedia extends Model
{
    /** @use HasFactory<PortfolioMediaFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_type' => PortfolioMediaType::class,
            'status' => ModerationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PortfolioItem, $this>
     */
    public function portfolioItem(): BelongsTo
    {
        return $this->belongsTo(PortfolioItem::class);
    }

    /**
     * Staff member who checked the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return MorphMany<Violation, $this>
     */
    public function violations(): MorphMany
    {
        return $this->morphMany(Violation::class, 'violatable');
    }
}
