<?php

namespace App\Enums;

/**
 * Who a learning content item is written for.
 */
enum ContentAudience: string
{
    case Freelancer = 'freelancer';
    case Employer = 'employer';
    case All = 'all';
}
