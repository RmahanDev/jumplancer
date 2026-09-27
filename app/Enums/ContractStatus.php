<?php

namespace App\Enums;

/**
 * Lifecycle of a contract.
 */
enum ContractStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Disputed = 'disputed';
}
