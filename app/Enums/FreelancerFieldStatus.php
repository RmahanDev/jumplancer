<?php

namespace App\Enums;

/**
 * Only active fields let a freelancer send proposals in that field.
 */
enum FreelancerFieldStatus: string
{
    case PendingExam = 'pending_exam';
    case Active = 'active';
    case Rejected = 'rejected';
}
