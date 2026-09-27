<?php

namespace App\Enums;

/**
 * Kind of mentorship session.
 */
enum MentorshipSessionType: string
{
    case Technical = 'technical';
    case Motivational = 'motivational';
    case Review = 'review';
}
