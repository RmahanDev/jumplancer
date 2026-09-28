<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\CategoryBudgetRange;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn (Skill $skill): array => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
                'category_id' => $skill->category_id,
            ])->values()),
            'budget_ranges' => $this->whenLoaded('budgetRanges', fn () => $this->budgetRanges->map(fn (CategoryBudgetRange $range): array => [
                'budget_type' => $range->budget_type->value,
                'min_amount' => $range->min_amount,
                'max_amount' => $range->max_amount,
                'is_active' => $range->is_active,
            ])->values()),
            'projects_count' => $this->whenCounted('projects'),
            'skills_count' => $this->whenCounted('skills'),
        ];
    }
}
