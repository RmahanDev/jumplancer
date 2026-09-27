<?php

namespace App\Enums;

/**
 * How a mentoring request is handled. Phone is only allowed for motivational tickets.
 */
enum TicketChannel: string
{
    case Ticket = 'ticket';
    case Phone = 'phone';
}
