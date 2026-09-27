<?php

namespace App\Enums;

/**
 * Seniority used by freelancer profiles, freelancer fields and field exams.
 */
enum ExperienceLevel: string
{
    case Beginner = 'beginner';
    case Junior = 'junior';
    case Intermediate = 'intermediate';
    case Senior = 'senior';
}
