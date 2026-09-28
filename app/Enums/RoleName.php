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
    case Support = 'support';

    /**
     * Staff roles open the admin panel; the rest are marketplace roles.
     */
    public function isStaff(): bool
    {
        return in_array($this, self::staff(), true);
    }

    /**
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::SuperAdmin, self::Admin, self::Support];
    }

    /**
     * @return list<string>
     */
    public static function staffValues(): array
    {
        return array_map(fn (self $role): string => $role->value, self::staff());
    }

    /**
     * Order used when a user holds several roles and one label must win (staff first).
     *
     * @return list<self>
     */
    public static function displayOrder(): array
    {
        return [self::SuperAdmin, self::Admin, self::Support, self::Mentor, self::Freelancer, self::Employer];
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
