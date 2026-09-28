<?php

namespace Tests\Feature\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\TransactionStatus;
use App\Models\Contract;
use App\Models\PortfolioMedia;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\BadgeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\PlatformSettingSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed([RoleSeeder::class, CategorySeeder::class, BadgeSeeder::class, PlatformSettingSeeder::class, DemoDataSeeder::class]);
    }

    public function test_every_dashboard_gets_a_demo_account(): void
    {
        foreach (['admin', 'mentor', 'freelancer', 'employer'] as $username) {
            $user = User::firstWhere('username', $username);

            $this->assertNotNull($user, $username);
            $this->assertTrue(Hash::check(DemoDataSeeder::PASSWORD, $user->password));
            $this->actingAs($user)->get(route('dashboard'))->assertRedirect();
        }
    }

    public function test_every_wallet_equals_the_sum_of_its_ledger(): void
    {
        foreach (Wallet::all() as $wallet) {
            $this->assertSame(
                (int) $wallet->balance,
                (int) $wallet->transactions()->where('status', TransactionStatus::Succeeded)->sum('amount'),
            );
        }

        $this->assertGreaterThan(0, Wallet::sum('held_balance'));
    }

    public function test_the_data_covers_every_state_the_dashboards_show(): void
    {
        $this->assertEqualsCanonicalizing(
            array_column(ProjectStatus::cases(), 'value'),
            Project::query()->distinct()->pluck('status')->map->value->all(),
        );
        $this->assertTrue(Contract::where('status', 'disputed')->exists());
        $this->assertTrue(Ticket::where('status', 'open')->whereNull('assigned_mentor_id')->exists());
        $this->assertTrue(PortfolioMedia::where('status', 'pending_review')->exists());
        $this->assertTrue(User::firstWhere('username', 'narges')->isSuspended());

        PortfolioMedia::all()->each(fn (PortfolioMedia $media) => Storage::disk('local')->assertExists($media->file_path));
    }

    public function test_seeding_again_does_nothing(): void
    {
        $users = User::count();
        $projects = Project::count();

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($users, User::count());
        $this->assertSame($projects, Project::count());
    }

    public function test_demo_accounts_open_every_page_of_their_dashboards(): void
    {
        $pages = [
            'admin' => ['admin.dashboard', 'admin.users.index', 'admin.admins.index', 'admin.projects.index', 'admin.contracts.index', 'admin.transactions.index', 'admin.categories.index', 'admin.plans.index', 'admin.tickets.index', 'admin.contents.index', 'admin.moderation.index', 'admin.settings.index'],
            'mentor' => ['mentor.dashboard', 'mentor.tickets.index', 'mentor.programs.index', 'mentor.reviews.index', 'mentor.contents.index', 'wallet.show', 'profile.edit'],
            'freelancer' => ['freelancer.dashboard', 'freelancer.projects.index', 'freelancer.proposals.index', 'freelancer.contracts.index', 'freelancer.portfolio.index', 'freelancer.fields.index', 'wallet.show', 'tickets.index', 'profile.edit'],
            'employer' => ['employer.dashboard', 'employer.projects.index', 'employer.proposals.index', 'employer.contracts.index', 'employer.plans.index', 'wallet.show', 'tickets.index', 'profile.edit'],
        ];

        foreach ($pages as $username => $routes) {
            $this->actingAs(User::firstWhere('username', $username));

            foreach ($routes as $route) {
                $this->get(route($route))->assertOk();
            }
        }
    }
}
