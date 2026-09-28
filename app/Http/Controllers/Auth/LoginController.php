<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /**
     * Show the login page (Blade + Bootstrap, works without JavaScript).
     */
    public function create(): View
    {
        return view('auth.login', [
            'demoAccounts' => app()->isLocal() ? $this->demoAccounts() : [],
        ]);
    }

    /**
     * Sign the user in and send them to their dashboard.
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        if ($user->isSuspended()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'identifier' => __('Your account is suspended. Please contact support.'),
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->toast(__('Welcome back, :name!', ['name' => $user->name]));

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Sign out. Inertia visits get a full-page redirect because the login page is Blade.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('login'));
    }

    /**
     * Seeded accounts shown on the local login page to speed up testing.
     *
     * @return list<array{label: string, username: string, password: string}>
     */
    private function demoAccounts(): array
    {
        return [
            ['label' => 'ادمین', 'username' => 'admin', 'password' => 'password'],
            ['label' => 'منتور', 'username' => 'mentor', 'password' => 'password'],
            ['label' => 'فریلنسر', 'username' => 'freelancer', 'password' => 'password'],
            ['label' => 'کارفرما', 'username' => 'employer', 'password' => 'password'],
        ];
    }
}
