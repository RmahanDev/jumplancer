<?php

namespace App\Enums;

/**
 * The five dashboards. Case order is the landing priority after login.
 */
enum Panel: string
{
    case SuperAdmin = 'super';
    case Admin = 'admin';
    case Mentor = 'mentor';
    case Freelancer = 'freelancer';
    case Employer = 'employer';

    /**
     * Route name of the panel's home page.
     */
    public function dashboardRoute(): string
    {
        return $this->value.'.dashboard';
    }

    /**
     * Roles that open this panel.
     *
     * @return list<RoleName>
     */
    public function roles(): array
    {
        return match ($this) {
            self::SuperAdmin => [RoleName::SuperAdmin],
            self::Admin => [RoleName::SuperAdmin, RoleName::Admin, RoleName::Support],
            self::Mentor => [RoleName::Mentor],
            self::Freelancer => [RoleName::Freelancer],
            self::Employer => [RoleName::Employer],
        };
    }

    /**
     * The spatie "role:" middleware guarding the panel's routes.
     */
    public function middleware(): string
    {
        return 'role:'.implode('|', array_map(fn (RoleName $role): string => $role->value, $this->roles()));
    }

    /**
     * Panel owning a route name such as "admin.users.index".
     */
    public static function fromRouteName(?string $routeName): ?self
    {
        return $routeName === null ? null : self::tryFrom(strstr($routeName, '.', true) ?: $routeName);
    }
}
