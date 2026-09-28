<?php

namespace App\Enums;

/**
 * A request to pay wallet money out to the user's bank card.
 */
enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
