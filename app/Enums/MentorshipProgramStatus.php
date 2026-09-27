<?php

namespace App\Enums;

/**
 * Lifecycle of a mentorship program.
 */
enum MentorshipProgramStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
