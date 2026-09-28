<?php

namespace Tests\Feature\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_creates_the_root_super_admin_from_the_environment(): void
    {
        config(['jumplancer.super_admin' => [
            'username' => 'mahan',
            'name' => 'ماهان',
            'email' => 'mahan@jumplancer.test',
            'password' => 'from-dot-env-only',
        ]]);

        $this->seed(SuperAdminSeeder::class);

        $mahan = User::firstWhere('username', 'mahan');

        $this->assertNotNull($mahan);
        $this->assertTrue($mahan->hasRole(RoleName::SuperAdmin));
        $this->assertTrue($mahan->isRootSuperAdmin());
        $this->assertTrue(Hash::check('from-dot-env-only', $mahan->password));
        $this->assertNotNull($mahan->email_verified_at);
    }

    public function test_the_password_is_not_hard_coded_anywhere_in_the_repository(): void
    {
        $seeder = file_get_contents(database_path('seeders/SuperAdminSeeder.php'));
        $config = file_get_contents(config_path('jumplancer.php'));
        $example = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString("env('SUPER_ADMIN_PASSWORD')", $config);
        $this->assertStringNotContainsString('Mm59608592', $seeder.$config.$example);
        $this->assertMatchesRegularExpression('/^SUPER_ADMIN_PASSWORD=$/m', $example);
    }

    public function test_skips_the_account_when_no_password_is_configured(): void
    {
        config(['jumplancer.super_admin.password' => null]);

        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_reseeding_never_resets_a_password_changed_from_the_dashboard(): void
    {
        config(['jumplancer.super_admin.password' => 'first-password']);
        $this->seed(SuperAdminSeeder::class);

        User::firstWhere('username', 'mahan')->update(['password' => 'changed-in-dashboard']);

        config(['jumplancer.super_admin.password' => 'first-password']);
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('changed-in-dashboard', User::firstWhere('username', 'mahan')->password));
    }
}
