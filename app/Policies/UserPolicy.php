<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may change which account. Staff accounts (admins, super admins) are only
 * touched from the admins page, and nobody can gain more power than they have.
 */
class UserPolicy
{
    /**
     * Edit a marketplace member (freelancer, employer, mentor) from the users page.
     */
    public function update(User $actor, User $member): Response
    {
        if (! $actor->can(AdminPermission::ManageUsers->value)) {
            return Response::deny();
        }

        if ($actor->is($member)) {
            return Response::deny(__('Use your profile page to change your own account.'));
        }

        if ($member->isStaff()) {
            return Response::deny(__('Staff accounts are managed from the admins page.'));
        }

        return Response::allow();
    }

    /**
     * Suspend, reactivate or delete a marketplace member.
     */
    public function delete(User $actor, User $member): Response
    {
        return $this->update($actor, $member);
    }

    /**
     * Change the role, permissions or status of a staff account.
     *
     * Super admins manage everyone except the root account. Other admins (with the
     * "admins.manage" permission) only manage admins whose permissions they also hold.
     */
    public function manageStaff(User $actor, User $staff): Response
    {
        if ($actor->is($staff)) {
            return Response::deny(__('You cannot change your own access.'));
        }

        if ($staff->isRootSuperAdmin()) {
            return Response::deny(__('The root super admin cannot be changed.'));
        }

        if ($actor->isSuperAdmin()) {
            return Response::allow();
        }

        if (! $actor->can(AdminPermission::ManageAdmins->value) || $staff->isSuperAdmin()) {
            return Response::deny(__('Only a super admin can manage this account.'));
        }

        $extraPermissions = array_diff($staff->staffPermissions(), $actor->staffPermissions());

        return $extraPermissions === []
            ? Response::allow()
            : Response::deny(__('This admin holds permissions that you do not have.'));
    }

    /**
     * Browse the platform as another member (super admins only, never as another super admin).
     */
    public function impersonate(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            && ! $target->isSuperAdmin()
            && ! $actor->is($target);
    }
}
