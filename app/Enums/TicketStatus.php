<?php

namespace App\Enums;

/**
 * Lifecycle of a mentoring ticket.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Closed = 'closed';
}
