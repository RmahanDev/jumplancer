<?php

namespace App\Http\Middleware;

use App\Enums\Panel;
use App\Models\User;
use App\Support\PanelNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'sandboxPayments' => (bool) config('jumplancer.payments.sandbox'),
            ],
            'auth' => fn (): array => ['user' => $user ? $this->userPayload($user) : null],
            'panel' => fn (): ?array => $user && $request->hasSession() ? $this->panelPayload($request, $user) : null,
            'impersonating' => fn (): ?array => $request->hasSession() && $request->session()->has('impersonator_id')
                ? ['name' => $user?->name, 'leaveUrl' => route('impersonation.destroy')]
                : null,
        ];
    }

    /**
     * @return array{id: int, name: string, username: ?string, email: string, avatar: ?string, roles: list<string>, permissions: list<string>, isSuperAdmin: bool}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->staffPermissions(),
            'isSuperAdmin' => $user->isSuperAdmin(),
        ];
    }

    /**
     * The dashboard being shown: taken from the route name ("admin.users.index" belongs to the admin panel).
     * Shared pages such as the wallet or profile keep the panel the user came from.
     *
     * @return array{current: ?string, available: list<array{key: string, href: string}>, navigation: list<array<string, mixed>>, profileUrl: string, logoutUrl: string}|null
     */
    private function panelPayload(Request $request, User $user): ?array
    {
        $available = $user->panels();
        $panel = Panel::fromRouteName($request->route()?->getName());

        if ($panel !== null && in_array($panel, $available, true)) {
            $request->session()->put('panel', $panel->value);
        } else {
            $remembered = Panel::tryFrom((string) $request->session()->get('panel'));
            $panel = in_array($remembered, $available, true) ? $remembered : $user->homePanel();
        }

        return [
            'current' => $panel?->value,
            'available' => array_map(fn (Panel $item): array => [
                'key' => $item->value,
                'href' => route($item->dashboardRoute()),
            ], $available),
            'navigation' => $panel ? PanelNavigation::for($user, $panel) : [],
            'profileUrl' => route('profile.edit'),
            'logoutUrl' => route('logout'),
        ];
    }
}
