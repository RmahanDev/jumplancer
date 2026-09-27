<?php

namespace App\Enums;

/**
 * Whether a learning content item is technical or motivational.
 */
enum ContentPurpose: string
{
    case Technical = 'technical';
    case Motivational = 'motivational';
}
