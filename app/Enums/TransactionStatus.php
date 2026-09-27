<?php

namespace App\Enums;

/**
 * Payment state of a ledger entry.
 */
enum TransactionStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
