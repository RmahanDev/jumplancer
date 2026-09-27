<?php

namespace App\Enums;

/**
 * Which side of the market a mentorship program serves.
 */
enum MentorshipTrack: string
{
    case Freelancer = 'freelancer';
    case Employer = 'employer';
}
