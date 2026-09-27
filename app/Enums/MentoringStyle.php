<?php

namespace App\Enums;

/**
 * Kind of mentoring a mentor offers.
 */
enum MentoringStyle: string
{
    case Technical = 'technical';
    case Motivational = 'motivational';
    case Both = 'both';
}
