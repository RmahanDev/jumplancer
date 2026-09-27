<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /**
     * A mentor works on tickets assigned to them, or takes an unassigned open ticket from the queue.
     */
    public function update(User $user, Ticket $ticket): Response
    {
        if ($ticket->assigned_mentor_id === $user->id) {
            return Response::allow();
        }

        return $ticket->assigned_mentor_id === null && $ticket->status === TicketStatus::Open
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
