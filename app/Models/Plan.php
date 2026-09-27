<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paid plan an employer buys once the free project posts are used.
 */
#[Fillable(['name', 'price', 'project_quota', 'duration_days', 'description', 'is_active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'project_quota' => 'integer',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EmployerSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(EmployerSubscription::class);
    }

    /**
     * Only plans that can currently be purchased.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
