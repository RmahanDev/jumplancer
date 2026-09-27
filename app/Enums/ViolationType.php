<?php

namespace App\Enums;

/**
 * Kind of off-platform contact attempt.
 */
enum ViolationType: string
{
    case Phone = 'phone';
    case Email = 'email';
    case Link = 'link';
    case SocialId = 'social_id';
    case Other = 'other';
}
