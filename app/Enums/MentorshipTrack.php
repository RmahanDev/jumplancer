<?php

namespace App\Enums;

/**
 * Who a mentorship program serves. Mentoring is only for freelancers.
 */
enum MentorshipTrack: string
{
    case Freelancer = 'freelancer';
}
