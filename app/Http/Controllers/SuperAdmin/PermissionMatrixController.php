<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AdminPermission;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every staff account against every permission, editable one cell at a time.
 */
class PermissionMatrixController extends Controller
{
    public function index(): Response
    {
        $staff = User::role([RoleName::SuperAdmin, RoleName::Admin])
            ->with(['roles', 'permissions'])
            ->orderBy('name')
            ->get();

        return Inertia::render('SuperAdmin/Permissions', [
            'staff' => StaffResource::collection($staff),
            'permissions' => AdminPermission::values(),
            'routes' => [
                'update' => route('super.permissions.update', ':id'),
                'admins' => route('admin.admins.index'),
            ],
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->isStaff(), 404);
        Gate::authorize('manageStaff', $staff);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermission::values())],
        ]);

        $staff->syncPermissions($validated['permissions']);

        $this->toast(__('Permissions of :name were updated.', ['name' => $staff->name]));

        return back();
    }
}
