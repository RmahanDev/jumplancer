<?php

namespace App\Enums;

/**
 * What the platform did about a violation.
 */
enum ViolationAction: string
{
    case MessageBlocked = 'message_blocked';
    case Warning = 'warning';
    case Suspended = 'suspended';
}
