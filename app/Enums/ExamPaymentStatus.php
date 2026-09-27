<?php

namespace App\Enums;

/**
 * Payment state of an assessment attempt. Paid exams can only start once paid.
 */
enum ExamPaymentStatus: string
{
    case Free = 'free';
    case Pending = 'pending';
    case Paid = 'paid';
}
