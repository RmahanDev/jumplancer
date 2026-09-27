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

    /**
     * Whether this level is the same as or above the given one (cases are ordered by seniority).
     */
    public function isAtLeast(self $level): bool
    {
        $order = array_flip(array_column(self::cases(), 'value'));

        return $order[$this->value] >= $order[$level->value];
    }
}
