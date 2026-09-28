<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
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
            'name' => $this->name,
            'price' => $this->price,
            'project_quota' => $this->project_quota,
            'duration_days' => $this->duration_days,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
        ];
    }
}
