<?php

namespace App\Enums;

/**
 * Lifecycle of a dispute handled by admins.
 */
enum DisputeStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
}
