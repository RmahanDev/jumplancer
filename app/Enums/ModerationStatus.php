<?php

namespace App\Enums;

/**
 * Review state of a portfolio upload; hidden from employers until approved.
 */
enum ModerationStatus: string
{
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
