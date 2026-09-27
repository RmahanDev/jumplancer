<?php

namespace App\Enums;

/**
 * Kind of mentoring request. Technical requests are always handled by ticket.
 */
enum TicketType: string
{
    case Technical = 'technical';
    case Motivational = 'motivational';
}
