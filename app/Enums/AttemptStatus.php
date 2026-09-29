<?php

namespace App\Enums;

/**
 * How an exam attempt stands: running, graded, or voided for leaving the exam page twice.
 */
enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Passed = 'passed';
    case Failed = 'failed';
    case Voided = 'voided';
}
