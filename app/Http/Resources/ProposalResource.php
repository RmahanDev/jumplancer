<?php

namespace App\Http\Resources;

use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Proposal
 */
class ProposalResource extends JsonResource
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
            'cover_letter' => $this->cover_letter,
            'proposed_price' => $this->proposed_price,
            'delivery_days' => $this->delivery_days,
            'status' => $this->status->value,
            'mentor_feedback' => $this->mentor_feedback,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'status' => $this->project->status->value,
                'budget_type' => $this->project->budget_type->value,
                'budget_min' => $this->project->budget_min,
                'budget_max' => $this->project->budget_max,
                'employer' => $this->project->relationLoaded('employer') ? UserResource::summary($this->project->employer) : null,
            ]),
            'freelancer' => $this->whenLoaded('freelancer', fn () => [
                ...UserResource::summary($this->freelancer),
                'level' => $this->freelancer->relationLoaded('freelancerProfile') ? $this->freelancer->freelancerProfile?->level->value : null,
                'readiness_score' => $this->freelancer->relationLoaded('freelancerProfile') ? $this->freelancer->freelancerProfile?->readiness_score : null,
            ]),
            'mentor_reviewer' => $this->whenLoaded('mentorReviewer', fn () => UserResource::summary($this->mentorReviewer)),
            'contract_id' => $this->whenLoaded('contract', fn () => $this->contract?->id),
        ];
    }
}
