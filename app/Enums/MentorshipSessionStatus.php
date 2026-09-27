<?php

namespace App\Enums;

/**
 * Lifecycle of a mentorship session.
 */
enum MentorshipSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Done = 'done';
    case Missed = 'missed';
    case Cancelled = 'cancelled';
}
