<?php

namespace App\Http\Resources;

use App\Models\Violation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Violation
 */
class ViolationResource extends JsonResource
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
            'violation_type' => $this->violation_type->value,
            'detected_content' => $this->detected_content,
            'detected_by' => $this->detected_by->value,
            'action_taken' => $this->action_taken->value,
            'violatable_type' => $this->violatable_type,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                ...UserResource::summary($this->user),
                'status' => $this->user->status->value,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => UserResource::summary($this->reviewer)),
        ];
    }
}
