<?php

namespace App\Http\Resources;

use App\Models\MentorshipProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MentorshipProgram
 */
class MentorshipProgramResource extends JsonResource
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
            'track' => $this->track->value,
            'goal' => $this->goal,
            'status' => $this->status->value,
            'is_free_mentorship' => $this->is_free_mentorship,
            'price' => $this->price,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'mentee' => $this->whenLoaded('mentee', fn () => UserResource::summary($this->mentee)),
            'mentor' => $this->whenLoaded('mentor', fn () => UserResource::summary($this->mentor)),
            'ticket' => $this->whenLoaded('ticket', fn () => $this->ticket ? ['id' => $this->ticket->id, 'subject' => $this->ticket->subject] : null),
            'sessions' => MentorshipSessionResource::collection($this->whenLoaded('sessions')),
            'sessions_count' => $this->whenCounted('sessions'),
        ];
    }
}
