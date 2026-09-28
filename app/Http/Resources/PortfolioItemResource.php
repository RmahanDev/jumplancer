<?php

namespace App\Http\Resources;

use App\Models\PortfolioItem;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PortfolioItem
 */
class PortfolioItemResource extends JsonResource
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
            'title' => $this->title,
            'role' => $this->role,
            'description' => $this->description,
            'outcome' => $this->outcome,
            'duration_days' => $this->duration_days,
            'is_visible' => $this->is_visible,
            'category_id' => $this->category_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn (Skill $skill): array => [
                'id' => $skill->id,
                'name' => $skill->name,
            ])->values()),
            'media' => PortfolioMediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
