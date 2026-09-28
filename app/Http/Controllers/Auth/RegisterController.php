<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    /**
     * Show the sign-up page for freelancers and employers.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Create the account with its role, profile and wallet, then sign in.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->safe()->only(['name', 'username', 'email', 'phone', 'password']));

            $user->assignRole(Role::findOrCreate($request->string('role')->toString(), 'web'));
            $user->ensureRoleProfiles();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        $this->toast(__('Welcome to Jump Lancer, :name!', ['name' => $user->name]));

        return redirect()->route('dashboard');
    }
}
