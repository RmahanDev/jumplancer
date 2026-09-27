<?php

namespace App\Enums;

/**
 * Platform roles managed by spatie/laravel-permission.
 */
enum RoleName: string
{
    case Admin = 'admin';
    case Employer = 'employer';
    case Freelancer = 'freelancer';
    case Mentor = 'mentor';
}
