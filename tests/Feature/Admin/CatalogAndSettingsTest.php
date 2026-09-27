<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Models\Category;
use App\Models\CategoryBudgetRange;
use App\Models\EmployerSubscription;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class CatalogAndSettingsTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->admin = $this->adminWith([AdminPermission::ManageCatalog, AdminPermission::ManageSettings]);
        $this->actingAs($this->admin);
    }

    public function test_categories_are_a_two_level_tree(): void
    {
        $this->post(route('admin.categories.store'), ['name' => 'هوش مصنوعی', 'slug' => 'AI', 'sort_order' => 5])->assertSessionHasNoErrors();
        $parent = Category::firstWhere('slug', 'ai');
        $this->assertNull($parent->parent_id);

        $this->post(route('admin.categories.store'), ['name' => 'یادگیری ماشین', 'slug' => 'machine-learning', 'parent_id' => $parent->id])->assertSessionHasNoErrors();
        $child = Category::firstWhere('slug', 'machine-learning');
        $this->assertSame($parent->id, $child->parent_id);

        // A sub-category cannot become a parent, and slugs are Latin.
        $this->post(route('admin.categories.store'), ['name' => 'عمیق', 'slug' => 'deep', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
        $this->post(route('admin.categories.store'), ['name' => 'فارسی', 'slug' => 'نامک فارسی'])->assertSessionHasErrors('slug');

        $this->put(route('admin.categories.update', $parent), ['name' => 'هوش مصنوعی', 'slug' => 'ai', 'parent_id' => $this->category('programming-tech')->id])
            ->assertSessionHasErrors(['parent_id' => 'دسته‌ای که زیردسته دارد نمی‌تواند زیرمجموعه‌ی دسته‌ی دیگری شود.']);
    }

    public function test_only_empty_categories_can_be_deleted(): void
    {
        $this->delete(route('admin.categories.destroy', $this->category('web-development')))
            ->assertSessionHasErrors('category');
        $this->assertModelExists($this->category('web-development'));

        $empty = Category::create(['name' => 'موقت', 'slug' => 'temporary']);
        $this->delete(route('admin.categories.destroy', $empty))->assertSessionHasNoErrors();
        $this->assertModelMissing($empty);
    }

    public function test_skills_belong_to_sub_categories(): void
    {
        $this->post(route('admin.skills.store'), ['category_id' => $this->category('web-development')->id, 'name' => 'Vue.js', 'slug' => 'vuejs'])
            ->assertSessionHasNoErrors();

        $skill = Skill::firstWhere('slug', 'vuejs');
        $this->assertSame('web-development', $skill->category()->first()->slug);

        $this->post(route('admin.skills.store'), ['category_id' => $this->category('programming-tech')->id, 'name' => 'Go', 'slug' => 'go'])
            ->assertSessionHasErrors('category_id');

        $this->put(route('admin.skills.update', $skill), ['category_id' => $skill->category_id, 'name' => 'Vue 3', 'slug' => 'vue'])->assertSessionHasNoErrors();
        $this->assertSame('Vue 3', $skill->fresh()->name);

        $this->delete(route('admin.skills.destroy', $skill))->assertSessionHasNoErrors();
        $this->assertModelMissing($skill);
    }

    public function test_budget_ranges_are_set_per_category_and_budget_type(): void
    {
        $category = $this->category('web-development');

        $this->put(route('admin.categories.budget-range', $category), ['budget_type' => 'fixed', 'min_amount' => 3_000_000, 'max_amount' => 90_000_000, 'is_active' => true])
            ->assertSessionHasNoErrors();
        $this->put(route('admin.categories.budget-range', $category), ['budget_type' => 'fixed', 'min_amount' => 4_000_000, 'max_amount' => null])
            ->assertSessionHasNoErrors();
        $this->put(route('admin.categories.budget-range', $category), ['budget_type' => 'fixed', 'min_amount' => 5_000_000, 'max_amount' => 1_000])
            ->assertSessionHasErrors('max_amount');

        $range = CategoryBudgetRange::where('category_id', $category->id)->sole();
        $this->assertSame(4_000_000, $range->min_amount);
        $this->assertNull($range->max_amount);
        $this->assertSame($this->admin->id, $range->updated_by);
    }

    public function test_plans_are_created_edited_and_protected_once_sold(): void
    {
        $this->post(route('admin.plans.store'), ['name' => 'پلن طلایی', 'price' => 1_500_000, 'project_quota' => 8, 'duration_days' => 120, 'is_active' => true])
            ->assertSessionHasNoErrors();

        $plan = Plan::firstWhere('name', 'پلن طلایی');
        $this->put(route('admin.plans.update', $plan), ['name' => 'پلن طلایی', 'price' => 1_700_000, 'project_quota' => null, 'duration_days' => null, 'is_active' => false])
            ->assertSessionHasNoErrors();
        $this->assertNull($plan->fresh()->project_quota);
        $this->assertFalse($plan->fresh()->is_active);

        EmployerSubscription::factory()->create(['plan_id' => $plan->id, 'employer_id' => $this->employer()->id]);
        $this->delete(route('admin.plans.destroy', $plan))->assertSessionHasErrors(['plan' => 'این پلن مشترک دارد؛ به‌جای حذف، غیرفعالش کن.']);
        $this->assertModelExists($plan);

        $unsold = Plan::create(['name' => 'پیش‌نویس', 'price' => 10_000]);
        $this->delete(route('admin.plans.destroy', $unsold))->assertSessionHasNoErrors();
        $this->assertModelMissing($unsold);
    }

    public function test_platform_settings_are_validated_by_type(): void
    {
        $free = PlatformSetting::firstWhere('setting_key', 'employer_free_projects');
        $fee = PlatformSetting::firstWhere('setting_key', 'extra_field_exam_fee');
        $level = PlatformSetting::firstWhere('setting_key', 'exam_required_from_level');
        $action = PlatformSetting::firstWhere('setting_key', 'contact_violation_action');

        $this->put(route('admin.settings.update', $free), ['setting_value' => 3])->assertSessionHasNoErrors();
        $this->put(route('admin.settings.update', $free), ['setting_value' => 'many'])->assertSessionHasErrors('setting_value');
        $this->put(route('admin.settings.update', $fee), ['setting_value' => 250_000])->assertSessionHasNoErrors();
        $this->put(route('admin.settings.update', $level), ['setting_value' => 'junior'])->assertSessionHasNoErrors();
        $this->put(route('admin.settings.update', $level), ['setting_value' => 'guru'])->assertSessionHasErrors('setting_value');
        $this->put(route('admin.settings.update', $action), ['setting_value' => 'warning'])->assertSessionHasNoErrors();

        $this->assertSame(3, $free->fresh()->typed_value);
        $this->assertSame(250_000, $fee->fresh()->typed_value);
        $this->assertSame('junior', $level->fresh()->typed_value);
        $this->assertSame($this->admin->id, $action->fresh()->updated_by);
    }

    public function test_catalog_needs_its_permission(): void
    {
        $this->actingAs($this->adminWith([AdminPermission::ManageUsers]));

        $this->post(route('admin.categories.store'), ['name' => 'x', 'slug' => 'x'])->assertForbidden();
        $this->post(route('admin.plans.store'), ['name' => 'x', 'price' => 1])->assertForbidden();
        $this->put(route('admin.settings.update', PlatformSetting::first()), ['setting_value' => 1])->assertForbidden();
    }
}
