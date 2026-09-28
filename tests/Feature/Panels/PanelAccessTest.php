<?php

namespace Tests\Feature\Panels;

use App\Enums\AdminPermission;
use App\Enums\RoleName;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /**
     * Every dashboard page, the React page it renders and the props it must send.
     *
     * @return array<string, array{string, string, string, list<string>}>
     */
    public static function pages(): array
    {
        return [
            'super dashboard' => ['super', 'super.dashboard', 'SuperAdmin/Dashboard', ['environment', 'health', 'tables', 'recentLogins']],
            'permission matrix' => ['super', 'super.permissions.index', 'SuperAdmin/Permissions', ['staff', 'permissions', 'routes.update']],
            'logs' => ['super', 'super.logs.index', 'SuperAdmin/Logs', ['entries', 'file', 'routes.clear']],
            'routes' => ['super', 'super.routes.index', 'SuperAdmin/Routes', ['routes']],
            'failed jobs' => ['super', 'super.jobs.index', 'SuperAdmin/Jobs', ['jobs.data', 'routes.retry']],
            'admin dashboard' => ['admin', 'admin.dashboard', 'Admin/Dashboard', ['stats', 'recent.users']],
            'users' => ['admin', 'admin.users.index', 'Admin/Users/Index', ['users.data', 'users.meta', 'filters', 'roles', 'routes.store']],
            'admins' => ['admin', 'admin.admins.index', 'Admin/Staff/Index', ['staff', 'permissions', 'grantable', 'routes.store']],
            'projects' => ['admin', 'admin.projects.index', 'Admin/Projects/Index', ['projects.data', 'statusCounts', 'categories', 'routes.review']],
            'contracts' => ['admin', 'admin.contracts.index', 'Admin/Contracts/Index', ['contracts.data', 'counts', 'routes.disputeUpdate']],
            'transactions' => ['admin', 'admin.transactions.index', 'Admin/Transactions/Index', ['transactions.data', 'summary', 'options.types']],
            'catalog' => ['admin', 'admin.categories.index', 'Admin/Catalog/Index', ['categories', 'budgetTypes', 'routes.skillStore']],
            'plans' => ['admin', 'admin.plans.index', 'Admin/Plans/Index', ['plans', 'routes.store']],
            'tickets' => ['admin', 'admin.tickets.index', 'Admin/Tickets/Index', ['tickets.data', 'mentors', 'routes.update']],
            'contents' => ['admin', 'admin.contents.index', 'Admin/Contents/Index', ['contents.data', 'categories', 'options.types']],
            'moderation' => ['admin', 'admin.moderation.index', 'Admin/Moderation/Index', ['media.data', 'counts', 'routes.media']],
            'settings' => ['admin', 'admin.settings.index', 'Admin/Settings/Index', ['settings', 'choices', 'routes.update']],
            'mentor dashboard' => ['mentor', 'mentor.dashboard', 'Mentor/Dashboard', ['stats', 'upcoming', 'sessionDates', 'profile']],
            'mentor tickets' => ['mentor', 'mentor.tickets.index', 'Mentor/Tickets/Index', ['tickets.data', 'counts', 'tracks']],
            'mentor programs' => ['mentor', 'mentor.programs.index', 'Mentor/Programs/Index', ['programs.data', 'options.sessionTypes']],
            'proposal reviews' => ['mentor', 'mentor.reviews.index', 'Mentor/Reviews/Index', ['proposals.data', 'counts']],
            'mentor contents' => ['mentor', 'mentor.contents.index', 'Mentor/Contents/Index', ['contents.data', 'categories']],
            'freelancer dashboard' => ['freelancer', 'freelancer.dashboard', 'Freelancer/Dashboard', ['stats', 'checklist', 'earnings', 'recommended']],
            'find projects' => ['freelancer', 'freelancer.projects.index', 'Freelancer/Projects/Index', ['projects.data', 'categories', 'activeFields', 'routes.propose']],
            'my proposals' => ['freelancer', 'freelancer.proposals.index', 'Freelancer/Proposals/Index', ['proposals.data', 'statusCounts']],
            'freelancer contracts' => ['freelancer', 'freelancer.contracts.index', 'Freelancer/Contracts/Index', ['contracts.data', 'routes.submit']],
            'portfolio' => ['freelancer', 'freelancer.portfolio.index', 'Freelancer/Portfolio/Index', ['items', 'categories', 'maxFiles']],
            'work fields' => ['freelancer', 'freelancer.fields.index', 'Freelancer/Fields/Index', ['fields', 'categories', 'levels', 'rules']],
            'employer dashboard' => ['employer', 'employer.dashboard', 'Employer/Dashboard', ['stats', 'posting', 'recentProposals']],
            'my projects' => ['employer', 'employer.projects.index', 'Employer/Projects/Index', ['projects.data', 'categories', 'posting', 'routes.store']],
            'received proposals' => ['employer', 'employer.proposals.index', 'Employer/Proposals/Index', ['proposals.data', 'projects', 'routes.hire']],
            'employer contracts' => ['employer', 'employer.contracts.index', 'Employer/Contracts/Index', ['contracts.data', 'balance', 'routes.milestoneStore']],
            'plans and subscription' => ['employer', 'employer.plans.index', 'Employer/Plans/Index', ['plans', 'subscriptions', 'free', 'next']],
            'wallet' => ['employer', 'wallet.show', 'Shared/Wallet', ['wallet', 'totals', 'transactions.data', 'sandbox']],
            'mentoring requests' => ['freelancer', 'tickets.index', 'Shared/Tickets', ['tickets.data', 'options.types']],
            'profile' => ['mentor', 'profile.edit', 'Shared/Profile', ['account', 'profiles', 'options', 'routes.password']],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_page_renders_its_react_component_with_its_props(string $role, string $route, string $component, array $props): void
    {
        $this->actingAs($this->userFor($role))
            ->get(route($route))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($component, $props): void {
                $page->component($component)
                    ->has('auth.user')
                    ->has('panel.navigation')
                    ->where('app.sandboxPayments', true);

                foreach ($props as $prop) {
                    $page->has($prop);
                }
            });
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function forbiddenPanels(): array
    {
        return [
            'admin' => ['admin', ['super.dashboard', 'mentor.dashboard', 'freelancer.dashboard', 'employer.dashboard']],
            'mentor' => ['mentor', ['super.dashboard', 'admin.dashboard', 'freelancer.dashboard', 'employer.dashboard']],
            'freelancer' => ['freelancer', ['super.dashboard', 'admin.dashboard', 'mentor.dashboard', 'employer.dashboard']],
            'employer' => ['employer', ['super.dashboard', 'admin.dashboard', 'mentor.dashboard', 'freelancer.dashboard']],
        ];
    }

    /**
     * @param  list<string>  $routes
     */
    #[DataProvider('forbiddenPanels')]
    public function test_roles_cannot_open_other_dashboards(string $role, array $routes): void
    {
        $this->actingAs($this->userFor($role));

        foreach ($routes as $route) {
            $this->get(route($route))
                ->assertForbidden()
                ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
        }
    }

    public function test_super_admins_open_the_admin_panel_too(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.current', 'admin')
                ->where('panel.available', fn ($panels) => collect($panels)->pluck('key')->all() === ['super', 'admin']));
    }

    public function test_admins_only_see_and_open_the_sections_they_have_permission_for(): void
    {
        $admin = $this->adminWith([AdminPermission::ManageUsers, AdminPermission::ViewFinance]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'panel.navigation',
                fn ($items) => collect($items)->pluck('key')->all() === ['admin.dashboard', 'admin.users.index', 'admin.transactions.index'],
            ));

        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.transactions.index'))->assertOk();
        $this->get(route('admin.admins.index'))->assertForbidden();
        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->get(route('admin.projects.index'))->assertForbidden();
    }

    public function test_members_with_two_roles_switch_between_their_dashboards(): void
    {
        $user = $this->freelancer();
        $user->assignRole(Role::findOrCreate(RoleName::Employer->value, 'web'));
        $user->ensureRoleProfiles();

        $this->actingAs($user)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.current', 'employer')
                ->where('panel.available', fn ($panels) => collect($panels)->pluck('key')->all() === ['freelancer', 'employer']));

        // Shared pages (wallet, profile) stay inside the dashboard the user came from.
        $this->get(route('wallet.show'))->assertInertia(fn (Assert $page) => $page->where('panel.current', 'employer'));
        $this->get(route('freelancer.dashboard'))->assertOk();
        $this->get(route('wallet.show'))->assertInertia(fn (Assert $page) => $page->where('panel.current', 'freelancer'));
    }

    public function test_admin_dashboard_charts_arrive_as_deferred_props(): void
    {
        $this->actingAs($this->adminWith())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('charts')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('charts.signups', 30)
                    ->has('charts.revenue', 26)
                    ->has('charts.projectsByStatus')
                    ->has('charts.usersByRole')));
    }

    public function test_platform_revenue_is_charted_per_persian_week(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-27 10:00'));
        $wallet = $this->employer()->wallet()->first();
        Transaction::factory()->for($wallet)->create(['type' => TransactionType::Fee, 'amount' => -250_000, 'created_at' => now()]);
        Transaction::factory()->for($wallet)->create(['type' => TransactionType::PlanPurchase, 'amount' => -1_000_000, 'created_at' => now()->subDays(40)]);
        Transaction::factory()->for($wallet)->create(['type' => TransactionType::Deposit, 'amount' => 9_000_000, 'created_at' => now()]);

        $this->actingAs($this->adminWith())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('charts.revenueLast30', 250_000)
                // 2026-09-27 is a Sunday: its week started on Saturday the 26th.
                ->where('charts.revenue.25', ['date' => '2026-09-26', 'value' => 250_000])
                ->where('charts.revenue.19', ['date' => '2026-08-15', 'value' => 1_000_000])
                ->where('charts.revenue.0.date', '2026-04-04')));
    }

    public function test_missing_pages_render_the_persian_error_page(): void
    {
        $this->actingAs($this->freelancer())
            ->get('/panel/freelancer/does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404)->has('auth.user'));
    }

    public function test_the_shared_user_payload_carries_roles_and_permissions(): void
    {
        $admin = $this->adminWith([AdminPermission::ManageContent]);

        $this->actingAs($admin)
            ->get(route('admin.contents.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.username', $admin->username)
                ->where('auth.user.roles', ['admin'])
                ->where('auth.user.permissions', ['content.manage'])
                ->where('auth.user.isSuperAdmin', false)
                ->where('panel.navigation.1.key', 'admin.contents.index')
                ->where('panel.navigation.1.active', true));
    }

    private function userFor(string $role): User
    {
        return match ($role) {
            'super' => $this->superAdmin(),
            'admin' => $this->adminWith(AdminPermission::cases()),
            'mentor' => $this->mentor(),
            'freelancer' => $this->freelancer(),
            'employer' => $this->employer(),
        };
    }
}
