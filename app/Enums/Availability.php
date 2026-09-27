<?php

namespace App\Enums;

/**
 * Whether a freelancer is taking new work.
 */
enum Availability: string
{
    case Available = 'available';
    case Busy = 'busy';
    case Unavailable = 'unavailable';
}
