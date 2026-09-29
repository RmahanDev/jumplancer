<?php

namespace App\Enums;

/**
 * Granular abilities of staff accounts (spatie/laravel-permission, guard "web").
 *
 * Super admins pass every check through Gate::before; plain admins only hold the
 * permissions a super admin (or an admin with ManageAdmins) granted them.
 */
enum AdminPermission: string
{
    case ManageUsers = 'users.manage';
    case ManageAdmins = 'admins.manage';
    case ManageProjects = 'projects.manage';
    case ManageContracts = 'contracts.manage';
    case ViewFinance = 'finance.view';
    case ManageCatalog = 'catalog.manage';
    case ManageModeration = 'moderation.manage';
    case ManageMentoring = 'mentoring.manage';
    case ManageContent = 'content.manage';
    case ManageSettings = 'settings.manage';
    case ManageWithdrawals = 'withdrawals.manage';
    case ManageExams = 'exams.manage';

    /**
     * What a new support agent can do until someone with "admins.manage" changes it.
     *
     * @return list<self>
     */
    public static function supportDefaults(): array
    {
        return [self::ManageMentoring, self::ManageWithdrawals];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
