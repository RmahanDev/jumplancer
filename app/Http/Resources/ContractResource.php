<?php

namespace App\Http\Resources;

use App\Enums\MilestoneStatus;
use App\Models\Contract;
use App\Models\Milestone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Employers never learn whether the freelancer has a mentor (nor the fee, which would give it away).
        $forEmployer = $request->routeIs('employer.*');

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'deposit_amount' => $this->deposit_amount,
            'deposit_balance' => $this->deposit_balance,
            'fee_percent' => $this->unless($forEmployer, fn () => $this->fee_percent),
            'mentorship_included' => $this->unless($forEmployer, fn () => $this->mentorship_included),
            'is_free_mentorship' => $this->unless($forEmployer, fn () => $this->is_free_mentorship),
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'title' => $this->project->title,
            ]),
            'employer' => $this->whenLoaded('employer', fn () => UserResource::summary($this->employer)),
            'freelancer' => $this->whenLoaded('freelancer', fn () => UserResource::summary($this->freelancer)),
            'mentor' => $this->when(! $forEmployer && $this->relationLoaded('mentor'), fn () => UserResource::summary($this->mentor)),
            'milestones' => MilestoneResource::collection($this->whenLoaded('milestones')),
            'progress' => $this->whenLoaded('milestones', fn (): array => $this->progress()),
            'reviewed' => $this->when(isset($this->reviewed_by_viewer), fn (): bool => (bool) $this->reviewed_by_viewer),
            'open_disputes_count' => $this->whenCounted('disputes'),
            'open_dispute' => $this->whenLoaded('openDispute', fn () => $this->openDispute ? [
                'id' => $this->openDispute->id,
                'status' => $this->openDispute->status->value,
                'reason' => $this->openDispute->reason,
                'raised_by_viewer' => $this->openDispute->raised_by === $request->user()?->id,
                'created_at' => $this->openDispute->created_at?->toIso8601String(),
            ] : null),
            'mentorship_ticket' => $this->whenLoaded('mentorshipTicket', fn () => $this->mentorshipTicket ? [
                'id' => $this->mentorshipTicket->id,
                'status' => $this->mentorshipTicket->status->value,
            ] : null),
        ];
    }

    /**
     * Money planned, locked in escrow and paid out across the milestones.
     *
     * @return array{planned: int, funded: int, released: int}
     */
    private function progress(): array
    {
        $escrowed = [MilestoneStatus::Funded, MilestoneStatus::Submitted, MilestoneStatus::Approved];

        return [
            'planned' => (int) $this->milestones->sum('amount'),
            'funded' => (int) $this->milestones->filter(fn (Milestone $milestone): bool => in_array($milestone->status, $escrowed, true))->sum('amount'),
            'released' => (int) $this->milestones->where('status', MilestoneStatus::Released)->sum('amount'),
        ];
    }
}
