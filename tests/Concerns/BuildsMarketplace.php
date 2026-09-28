<?php

namespace Tests\Concerns;

use App\Enums\AdminPermission;
use App\Enums\ExperienceLevel;
use App\Enums\FreelancerFieldStatus;
use App\Enums\ProjectStatus;
use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use App\Services\WalletLedger;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PlatformSettingSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

/**
 * Accounts and records for dashboard tests, built the way the application builds them.
 *
 * Users are returned refreshed from the database: actingAs() marks models as "not recently
 * created", and in strict mode reading a column the factory never set would then throw.
 */
trait BuildsMarketplace
{
    private static int $sequence = 0;

    protected function seedReferenceData(): void
    {
        $this->seed([RoleSeeder::class, CategorySeeder::class, PlatformSettingSeeder::class]);
    }

    protected function category(string $slug): Category
    {
        return Category::where('slug', $slug)->firstOrFail();
    }

    protected function superAdmin(array $attributes = []): User
    {
        $user = User::factory()->create(['username' => $this->nextUsername('dev'), ...$attributes]);
        $user->assignRole(Role::findOrCreate(RoleName::SuperAdmin->value, 'web'));

        return $user->refresh();
    }

    protected function rootSuperAdmin(): User
    {
        return $this->superAdmin(['username' => config('jumplancer.super_admin.username')]);
    }

    /**
     * @param  list<AdminPermission>  $permissions
     */
    protected function adminWith(array $permissions = []): User
    {
        $user = User::factory()->admin()->create(['username' => $this->nextUsername('admin')]);
        $user->givePermissionTo(array_map(fn (AdminPermission $permission): string => $permission->value, $permissions));

        return $user->refresh();
    }

    protected function freelancer(ExperienceLevel $level = ExperienceLevel::Beginner, ?string $activeField = 'programming-tech'): User
    {
        $user = User::factory()->freelancer()->create(['username' => $this->nextUsername('freelancer')]);
        $user->freelancerProfile()->first()->update(['level' => $level]);
        $user->ensureWallet();

        if ($activeField !== null) {
            $user->freelancerFields()->create([
                'category_id' => $this->category($activeField)->id,
                'claimed_level' => $level,
                'is_primary' => true,
                'status' => FreelancerFieldStatus::Active,
                'verified_at' => now(),
            ]);
        }

        return $user->refresh();
    }

    protected function employer(int $balance = 0): User
    {
        $user = User::factory()->employer()->create(['username' => $this->nextUsername('employer')]);
        $user->ensureWallet();

        if ($balance > 0) {
            app(WalletLedger::class)->deposit($user, $balance);
        }

        return $user->refresh();
    }

    protected function mentor(): User
    {
        $user = User::factory()->mentor()->create(['username' => $this->nextUsername('mentor')]);
        $user->mentorProfile()->first()->forceFill(['is_verified' => true])->save();
        $user->ensureWallet();

        return $user->refresh();
    }

    /**
     * An open project in "web development" (a sub-category of "programming & tech").
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function openProject(User $employer, array $attributes = []): Project
    {
        return Project::factory()->create([
            'employer_id' => $employer->id,
            'category_id' => $this->category('web-development')->id,
            'status' => ProjectStatus::Open,
            'budget_min' => 5_000_000,
            'budget_max' => 8_000_000,
            ...$attributes,
        ]);
    }

    private function nextUsername(string $prefix): string
    {
        return $prefix.(++self::$sequence);
    }
}
