<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserStatusController extends Controller
{
    /**
     * Suspend, ban or reactivate a member. Suspended members are signed out on their next request.
     */
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)],
            'reason' => ['nullable', 'required_unless:status,'.UserStatus::Active->value, 'string', 'max:255'],
        ]);

        $status = UserStatus::from($validated['status']);

        $user->forceFill([
            'status' => $status,
            'suspended_at' => $status === UserStatus::Active ? null : now(),
            'suspension_reason' => $status === UserStatus::Active ? null : $validated['reason'],
        ])->save();

        $this->toast(
            $status === UserStatus::Active
                ? __('Account of :name is active again.', ['name' => $user->name])
                : __('Account of :name was suspended.', ['name' => $user->name]),
            $status === UserStatus::Active ? 'success' : 'warning',
        );

        return back();
    }
}
