<?php

namespace App\Models;

use App\Enums\BudgetType;
use Database\Factories\CategoryBudgetRangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-editable budget floor/ceiling per category. A child category row overrides its parent.
 */
#[Fillable(['category_id', 'budget_type', 'min_amount', 'max_amount', 'is_active', 'updated_by'])]
class CategoryBudgetRange extends Model
{
    /** @use HasFactory<CategoryBudgetRangeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget_type' => BudgetType::class,
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Admin who last changed the range.
     *
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
