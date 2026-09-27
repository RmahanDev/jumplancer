<?php

namespace App\Enums;

/**
 * Escrow state of a milestone. Funded means the money is held in escrow.
 */
enum MilestoneStatus: string
{
    case Pending = 'pending';
    case Funded = 'funded';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Released = 'released';
    case Refunded = 'refunded';
}
