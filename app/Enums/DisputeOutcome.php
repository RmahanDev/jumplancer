<?php

namespace App\Enums;

/**
 * The expert's decision on a dispute, i.e. what happens to the money held for the contract.
 */
enum DisputeOutcome: string
{
    /** Nothing moves; the contract goes back to work. */
    case Continue = 'continue';

    /** The freelancer fell short: everything held (deposit and funded milestones) returns to the employer. */
    case RefundEmployer = 'refund_employer';

    /** The work was delivered properly: everything held is paid to the freelancer (minus the platform fee). */
    case PayFreelancer = 'pay_freelancer';
}
