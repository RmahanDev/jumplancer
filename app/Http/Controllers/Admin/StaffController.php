<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Admins and super admins with their permissions ("admins.manage").
 */
class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        $staff = User::role([RoleName::SuperAdmin, RoleName::Admin])
            ->with(['roles', 'permissions'])
            ->orderByDesc('id')
            ->get()
            ->sortByDesc(fn (User $user): int => ($user->isRootSuperAdmin() ? 2 : 0) + ($user->isSuperAdmin() ? 1 : 0))
            ->values();

        return Inertia::render('Admin/Staff/Index', [
            'staff' => StaffResource::collection($staff),
            'permissions' => AdminPermission::values(),
            'grantable' => $actor->staffPermissions(),
            'canCreateSuperAdmin' => $actor->isSuperAdmin(),
            'routes' => [
                'store' => route('admin.admins.store'),
                'update' => route('admin.admins.update', ':id'),
                'destroy' => route('admin.admins.destroy', ':id'),
            ],
        ]);
    }

    public function store(SaveStaffRequest $request): RedirectResponse
    {
        $staff = DB::transaction(function () use ($request): User {
            $staff = User::create($request->safe()->only(['name', 'username', 'email', 'phone', 'password']));
            $staff->forceFill(['email_verified_at' => now()])->save();
            $this->applyAccess($staff, $request);
            $staff->ensureRoleProfiles();

            return $staff;
        });

        $this->toast(__('Admin :name was created.', ['name' => $staff->name]));

        return back();
    }

    public function update(SaveStaffRequest $request, User $staff): RedirectResponse
    {
        DB::transaction(function () use ($request, $staff): void {
            $staff->fill($request->safe()->only(['name', 'username', 'email', 'phone']));

            if ($request->filled('password')) {
                $staff->password = $request->validated('password');
            }

            $status = UserStatus::from($request->validated('status'));
            $staff->forceFill([
                'status' => $status,
                'suspended_at' => $status === UserStatus::Active ? null : ($staff->suspended_at ?? now()),
            ])->save();

            $this->applyAccess($staff, $request);
        });

        $this->toast(__('Admin :name was updated.', ['name' => $staff->name]));

        return back();
    }

    public function destroy(User $staff): RedirectResponse
    {
        abort_unless($staff->isStaff(), 404);
        Gate::authorize('manageStaff', $staff);

        $staff->delete();

        $this->toast(__('Admin :name was removed.', ['name' => $staff->name]));

        return back();
    }

    private function applyAccess(User $staff, SaveStaffRequest $request): void
    {
        $role = $request->boolean('is_super_admin') ? RoleName::SuperAdmin : RoleName::Admin;

        $staff->syncRoles([Role::findOrCreate($role->value, 'web')]);
        $staff->syncPermissions($request->grantedPermissions());
    }
}
