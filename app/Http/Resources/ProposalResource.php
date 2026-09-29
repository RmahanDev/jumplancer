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
        // Mentoring is between the freelancer and the platform: employers never see any trace of it.
        $forEmployer = $request->routeIs('employer.*');

        return [
            'id' => $this->id,
            'cover_letter' => $this->cover_letter,
            'proposed_price' => $this->proposed_price,
            'delivery_days' => $this->delivery_days,
            'mentorship_requested' => $this->unless($forEmployer, fn () => $this->mentorship_requested),
            'status' => $this->status->value,
            'mentor_feedback' => $this->unless($forEmployer, fn () => $this->mentor_feedback),
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
                // Skills proven by passing their exam (loaded already filtered to verified ones).
                'verified_skills' => $this->freelancer->relationLoaded('skills')
                    ? $this->freelancer->skills->map(fn ($skill): array => ['id' => $skill->id, 'name' => $skill->name])->values()
                    : [],
            ]),
            'mentor_reviewer' => $this->when(! $forEmployer && $this->relationLoaded('mentorReviewer'), fn () => UserResource::summary($this->mentorReviewer)),
            'contract_id' => $this->whenLoaded('contract', fn () => $this->contract?->id),
        ];
    }
}
