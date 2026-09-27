<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a super admin see the platform through another member's eyes (debugging and support).
 */
class ImpersonationController extends Controller
{
    /**
     * Start browsing as the given user.
     */
    public function store(Request $request, User $user): Response
    {
        Gate::authorize('impersonate', $user);

        $request->session()->put('impersonator_id', $request->user()->id);
        $request->session()->forget('panel');
        Auth::login($user);

        $this->toast(__('You are now browsing as :name.', ['name' => $user->name]), 'info');

        return Inertia::location(route('dashboard'));
    }

    /**
     * Return to the super admin account.
     */
    public function destroy(Request $request): Response
    {
        $impersonator = User::find($request->session()->pull('impersonator_id'));

        abort_unless($impersonator?->isSuperAdmin(), 403);

        $request->session()->forget('panel');
        Auth::login($impersonator);

        $this->toast(__('Welcome back, :name!', ['name' => $impersonator->name]), 'info');

        return Inertia::location(route('super.dashboard'));
    }
}
