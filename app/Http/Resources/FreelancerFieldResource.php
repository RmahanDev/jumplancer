<?php

namespace App\Http\Resources;

use App\Models\FreelancerField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FreelancerField
 */
class FreelancerFieldResource extends JsonResource
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
            'category_id' => $this->category_id,
            'claimed_level' => $this->claimed_level->value,
            'is_primary' => $this->is_primary,
            'exam_required' => $this->exam_required,
            'exam_fee_required' => $this->exam_fee_required,
            'status' => $this->status->value,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'freelancer' => $this->whenLoaded('freelancer', fn () => UserResource::summary($this->freelancer)),
        ];
    }
}
