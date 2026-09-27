<?php

namespace Tests\Feature\Seeders;

use App\Enums\RoleName;
use App\Models\Badge;
use App\Models\Category;
use App\Models\PlatformSetting;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_roles_category_tree_skills_badges_and_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing(
            array_column(RoleName::cases(), 'value'),
            Role::pluck('name')->all(),
        );
        $this->assertSame(4, Category::topLevel()->count());
        $this->assertSame(8, Category::whereNotNull('parent_id')->count());
        $this->assertSame('web-development', Skill::firstWhere('slug', 'laravel')->category->slug);
        $this->assertSame(5, Badge::count());
        $this->assertSame(30, PlatformSetting::firstWhere('setting_key', 'second_free_project_window_days')->typed_value);
        $this->assertNull(PlatformSetting::firstWhere('setting_key', 'extra_field_exam_fee')->setting_value);
    }

    public function test_seeding_twice_does_not_duplicate_reference_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Role::count());
        $this->assertSame(12, Category::count());
        $this->assertSame(21, Skill::count());
        $this->assertSame(5, Badge::count());
        $this->assertSame(7, PlatformSetting::count());
    }

    public function test_reseeding_keeps_settings_changed_from_the_admin_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);
        PlatformSetting::where('setting_key', 'beginner_free_mentorships')->update(['setting_value' => '3']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, PlatformSetting::firstWhere('setting_key', 'beginner_free_mentorships')->typed_value);
    }

    public function test_does_not_create_demo_accounts_outside_local_environment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
    }
}
