<?php

namespace App\Enums;

/**
 * Lifecycle of a project posted by an employer.
 */
enum ProjectStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
