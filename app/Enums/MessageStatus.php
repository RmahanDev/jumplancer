<?php

namespace App\Enums;

/**
 * Blocked messages contained contact info and are never shown to the other side.
 */
enum MessageStatus: string
{
    case Delivered = 'delivered';
    case Blocked = 'blocked';
}
