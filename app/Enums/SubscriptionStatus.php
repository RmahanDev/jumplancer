<?php

namespace App\Enums;

/**
 * State of an employer plan subscription.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
