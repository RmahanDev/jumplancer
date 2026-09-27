<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
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
            'ticket_type' => $this->ticket_type->value,
            'channel' => $this->channel->value,
            'phone_number' => $this->phone_number,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status->value,
            'assigned_mentor_id' => $this->assigned_mentor_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'requester' => $this->whenLoaded('requester', fn () => UserResource::summary($this->requester)),
            'assigned_mentor' => $this->whenLoaded('assignedMentor', fn () => UserResource::summary($this->assignedMentor)),
            'programs_count' => $this->whenCounted('mentorshipPrograms'),
        ];
    }
}
