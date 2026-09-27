<?php

namespace App\Enums;

/**
 * Proficiency of a freelancer in a single skill (skill_user pivot).
 */
enum SkillLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
}
