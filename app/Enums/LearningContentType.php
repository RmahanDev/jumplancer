<?php

namespace App\Enums;

/**
 * Format of a learning content item.
 */
enum LearningContentType: string
{
    case Article = 'article';
    case Video = 'video';
    case Checklist = 'checklist';
    case Roadmap = 'roadmap';
}
