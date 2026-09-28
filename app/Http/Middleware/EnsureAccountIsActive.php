<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out users whose account was suspended or banned while they were logged in.
 */
class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isSuspended() || $request->session()->has('impersonator_id')) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'identifier' => __('Your account is suspended. Please contact support.'),
        ])));

        // A full page visit: the login page is a Blade view, not an Inertia component.
        return Inertia::location(route('login'));
    }
}
