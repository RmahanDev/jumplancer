<?php

namespace App\Enums;

/**
 * A single skill test, or an entry exam for a whole field.
 */
enum AssessmentScope: string
{
    case Skill = 'skill';
    case Field = 'field';
}
