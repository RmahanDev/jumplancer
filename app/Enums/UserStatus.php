<?php

namespace App\Enums;

/**
 * Account state of a user.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';
}
