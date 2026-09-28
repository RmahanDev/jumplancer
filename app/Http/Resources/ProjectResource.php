<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
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
            'description' => $this->description,
            'status' => $this->status->value,
            'budget_type' => $this->budget_type->value,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'is_beginner_friendly' => $this->is_beginner_friendly,
            'deadline' => $this->deadline?->toDateString(),
            'posting_type' => $this->posting_type->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'review_note' => $this->review_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'employer' => $this->whenLoaded('employer', fn () => UserResource::summary($this->employer)),
            // The freelancer working on the project once someone was hired.
            'freelancer' => $this->whenLoaded('currentContract', fn () => $this->currentContract ? UserResource::summary($this->currentContract->freelancer) : null),
            'contract' => $this->whenLoaded('currentContract', fn () => $this->currentContract ? [
                'id' => $this->currentContract->id,
                'status' => $this->currentContract->status->value,
                'amount' => $this->currentContract->amount,
                'mentorship_included' => $this->currentContract->mentorship_included,
            ] : null),
            'proposals' => ProposalResource::collection($this->whenLoaded('proposals')),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn (Skill $skill): array => [
                'id' => $skill->id,
                'name' => $skill->name,
            ])->values()),
            'proposals_count' => $this->whenCounted('proposals'),
            'has_proposed' => $this->when(isset($this->has_proposed), fn (): bool => (bool) $this->has_proposed),
        ];
    }
}
