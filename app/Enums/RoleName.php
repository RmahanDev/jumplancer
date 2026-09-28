<?php

namespace App\Enums;

/**
 * Platform roles managed by spatie/laravel-permission.
 */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Employer = 'employer';
    case Freelancer = 'freelancer';
    case Mentor = 'mentor';

    /**
     * Staff roles open the admin panel; the rest are marketplace roles.
     */
    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    /**
     * Roles a visitor may pick on the public registration form.
     *
     * @return list<self>
     */
    public static function selfRegistrable(): array
    {
        return [self::Freelancer, self::Employer];
    }
}
