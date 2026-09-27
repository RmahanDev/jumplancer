<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMemberRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Marketplace members: freelancers, employers and mentors.
 */
class UserController extends Controller
{
    /**
     * Columns the table can be sorted by (an allow-list, never raw input).
     */
    private const SORTABLE = ['name', 'created_at', 'last_login_at'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(SaveMemberRequest::memberRoles())],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $users = User::query()
            ->with(['roles', 'freelancerProfile', 'employerProfile', 'mentorProfile'])
            ->whereDoesntHave('roles', fn (Builder $query) => $query->whereIn('name', [RoleName::SuperAdmin->value, RoleName::Admin->value]))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.PersianText::normalize($search).'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('username', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => UserResource::collection($users),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'role' => $filters['role'] ?? null,
                'status' => $filters['status'] ?? null,
                'sort' => $filters['sort'] ?? 'created_at',
                'direction' => $filters['direction'] ?? 'desc',
            ],
            'counts' => [
                'all' => User::whereDoesntHave('roles', fn (Builder $query) => $query->whereIn('name', [RoleName::SuperAdmin->value, RoleName::Admin->value]))->count(),
                'suspended' => User::whereIn('status', [UserStatus::Suspended, UserStatus::Banned])->count(),
            ],
            'roles' => SaveMemberRequest::memberRoles(),
            'canImpersonate' => $request->user()->isSuperAdmin(),
            'routes' => [
                'store' => route('admin.users.store'),
                'update' => route('admin.users.update', ':id'),
                'destroy' => route('admin.users.destroy', ':id'),
                'status' => route('admin.users.status', ':id'),
                'impersonate' => route('super.impersonate', ':id'),
            ],
        ]);
    }

    public function store(SaveMemberRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->safe()->only(['name', 'username', 'email', 'phone', 'password']));
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->syncRoles($user, $request->validated('roles'));

            return $user;
        });

        $this->toast(__('Account of :name was created.', ['name' => $user->name]));

        return back();
    }

    public function update(SaveMemberRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user->fill($request->safe()->only(['name', 'username', 'email', 'phone']));

            if ($request->filled('password')) {
                $user->password = $request->validated('password');
            }

            $user->save();
            $this->syncRoles($user, $request->validated('roles'));
        });

        $this->toast(__('Account of :name was updated.', ['name' => $user->name]));

        return back();
    }

    /**
     * Soft delete: history (projects, contracts, ledger) stays intact.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        $this->toast(__('Account of :name was deleted.', ['name' => $user->name]));

        return back();
    }

    /**
     * @param  list<string>  $roles
     */
    private function syncRoles(User $user, array $roles): void
    {
        $user->syncRoles(array_map(fn (string $role): Role => Role::findOrCreate($role, 'web'), $roles));
        $user->ensureRoleProfiles();

        // Mentors added by staff are vouched for by the platform.
        if (in_array(RoleName::Mentor->value, $roles, true)) {
            $user->mentorProfile()->update(['is_verified' => true]);
        }
    }
}
