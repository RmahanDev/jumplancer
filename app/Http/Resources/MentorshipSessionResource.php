<?php

namespace App\Http\Resources;

use App\Models\MentorshipSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MentorshipSession
 */
class MentorshipSessionResource extends JsonResource
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
            'program_id' => $this->program_id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'session_type' => $this->session_type->value,
            'status' => $this->status->value,
            'meeting_link' => $this->meeting_link,
            'mentor_notes' => $this->mentor_notes,
            'mentee_rating' => $this->mentee_rating,
            'mentee' => $this->whenLoaded('program', fn () => $this->program->relationLoaded('mentee') ? UserResource::summary($this->program->mentee) : null),
        ];
    }
}
