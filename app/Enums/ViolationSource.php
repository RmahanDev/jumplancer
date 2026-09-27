<?php

namespace App\Enums;

/**
 * How a violation was detected.
 */
enum ViolationSource: string
{
    case AutoFilter = 'auto_filter';
    case Staff = 'staff';
    case UserReport = 'user_report';
}
