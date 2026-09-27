<?php

namespace App\Http\Resources;

use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dispute
 */
class DisputeResource extends JsonResource
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
            'reason' => $this->reason,
            'status' => $this->status->value,
            'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'contract' => $this->whenLoaded('contract', fn () => [
                'id' => $this->contract->id,
                'amount' => $this->contract->amount,
                'project_title' => $this->contract->relationLoaded('project') ? $this->contract->project->title : null,
                'employer' => $this->contract->relationLoaded('employer') ? UserResource::summary($this->contract->employer) : null,
                'freelancer' => $this->contract->relationLoaded('freelancer') ? UserResource::summary($this->contract->freelancer) : null,
            ]),
            'initiator' => $this->whenLoaded('initiator', fn () => UserResource::summary($this->initiator)),
            'resolver' => $this->whenLoaded('resolver', fn () => UserResource::summary($this->resolver)),
        ];
    }
}
