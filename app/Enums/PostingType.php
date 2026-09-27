<?php

namespace App\Enums;

/**
 * How a project post was paid for.
 */
enum PostingType: string
{
    case FreeFirst = 'free_first';
    case FreeSecond = 'free_second';
    case Subscription = 'subscription';
}
