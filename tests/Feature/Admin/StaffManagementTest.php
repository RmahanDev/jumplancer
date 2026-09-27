<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * The super admin exists before anyone else; admins are created from the dashboard with
 * granular permissions, and "create admins" (admins.manage) is itself one of them.
 */
class StaffManagementTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /**
     * @param  list<string>  $permissions
     * @return array<string, mixed>
     */
    private function staffPayload(string $username, array $permissions = [], bool $superAdmin = false): array
    {
        return [
            'name' => 'ادمین تازه',
            'username' => $username,
            'email' => "{$username}@example.com",
            'phone' => null,
            'password' => 'secret123',
            'is_super_admin' => $superAdmin,
            'status' => 'active',
            'permissions' => $permissions,
        ];
    }

    public function test_super_admin_creates_an_admin_with_chosen_permissions(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.admins.store'), $this->staffPayload('support', ['users.manage', 'mentoring.manage']))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        $admin = User::firstWhere('username', 'support');

        $this->assertTrue($admin->hasRole(RoleName::Admin));
        $this->assertEqualsCanonicalizing(['users.manage', 'mentoring.manage'], $admin->staffPermissions());
        $this->assertTrue($admin->can('users.manage'));
        $this->assertFalse($admin->can('finance.view'));
    }

    public function test_super_admin_can_create_another_super_admin_with_full_access(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.admins.store'), $this->staffPayload('cto', ['users.manage'], superAdmin: true))
            ->assertSessionHasNoErrors();

        $cto = User::firstWhere('username', 'cto');

        $this->assertTrue($cto->isSuperAdmin());
        $this->assertSame([], $cto->getDirectPermissions()->pluck('name')->all(), 'super admins hold every permission implicitly');

        foreach (AdminPermission::cases() as $permission) {
            $this->assertTrue($cto->can($permission->value));
        }

        $this->actingAs($cto)->get(route('super.dashboard'))->assertOk();
    }

    public function test_creating_admins_is_itself_a_permission(): void
    {
        $admin = $this->adminWith([AdminPermission::ManageUsers]);

        $this->actingAs($admin)->get(route('admin.admins.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.admins.store'), $this->staffPayload('helper'))->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'helper']);
    }

    public function test_admins_with_that_permission_create_admins_within_their_own_access(): void
    {
        $manager = $this->adminWith([AdminPermission::ManageAdmins, AdminPermission::ManageUsers, AdminPermission::ManageContent]);

        $this->actingAs($manager)
            ->post(route('admin.admins.store'), $this->staffPayload('writer', ['content.manage']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['content.manage'], User::firstWhere('username', 'writer')->staffPermissions());

        // The new admin can even receive "admins.manage" when the creator holds it.
        $this->actingAs($manager)
            ->post(route('admin.admins.store'), $this->staffPayload('deputy', ['admins.manage', 'users.manage']))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['admins.manage', 'users.manage'], User::firstWhere('username', 'deputy')->staffPermissions());
    }

    public function test_nobody_hands_out_a_permission_they_do_not_hold(): void
    {
        $manager = $this->adminWith([AdminPermission::ManageAdmins, AdminPermission::ManageUsers]);

        $this->actingAs($manager)
            ->post(route('admin.admins.store'), $this->staffPayload('sneaky', ['users.manage', 'finance.view']))
            ->assertSessionHasErrors(['permissions.1' => 'فقط دسترسی‌هایی را می‌توانی بدهی که خودت داری.']);

        $this->assertDatabaseMissing('users', ['username' => 'sneaky']);
    }

    public function test_only_super_admins_create_super_admins(): void
    {
        $manager = $this->adminWith(AdminPermission::cases());

        $this->actingAs($manager)
            ->post(route('admin.admins.store'), $this->staffPayload('boss', [], superAdmin: true))
            ->assertSessionHasErrors(['is_super_admin' => 'فقط مدیر کل می‌تواند مدیر کل دیگری تعریف کند.']);

        $this->assertDatabaseMissing('users', ['username' => 'boss']);
    }

    public function test_admins_cannot_touch_super_admins_or_admins_with_more_access(): void
    {
        $manager = $this->adminWith([AdminPermission::ManageAdmins, AdminPermission::ManageUsers]);
        $superAdmin = $this->superAdmin();
        $financeAdmin = $this->adminWith([AdminPermission::ViewFinance]);

        $this->actingAs($manager);

        $this->put(route('admin.admins.update', $superAdmin), $this->staffPayload($superAdmin->username))->assertForbidden();
        $this->delete(route('admin.admins.destroy', $superAdmin))->assertForbidden();
        $this->put(route('admin.admins.update', $financeAdmin), $this->staffPayload($financeAdmin->username, ['users.manage']))->assertForbidden();
        $this->delete(route('admin.admins.destroy', $financeAdmin))->assertForbidden();

        $this->assertNotSoftDeleted($superAdmin);
        $this->assertTrue($financeAdmin->fresh()->can('finance.view'));
    }

    public function test_the_admins_page_explains_why_an_account_is_locked(): void
    {
        $manager = $this->adminWith([AdminPermission::ManageAdmins]);
        $root = $this->rootSuperAdmin();
        $peer = $this->adminWith([AdminPermission::ManageAdmins]);

        $this->actingAs($manager)
            ->get(route('admin.admins.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Staff/Index')
                ->where('canCreateSuperAdmin', false)
                ->where('grantable', ['admins.manage'])
                ->has('staff', 3)
                ->where('staff.0.username', $root->username)
                ->where('staff.0.is_root', true)
                ->where('staff.0.can_manage', false)
                ->where('staff.0.locked_reason', 'مدیر کلِ اصلی قابل تغییر نیست.')
                ->where('staff', fn ($staff) => collect($staff)->firstWhere('username', $peer->username)['can_manage'] === true)
                ->where('staff', fn ($staff) => collect($staff)->firstWhere('username', $manager->username)['is_you'] === true));
    }

    public function test_the_root_super_admin_cannot_be_changed_by_anyone(): void
    {
        $root = $this->rootSuperAdmin();
        $otherSuperAdmin = $this->superAdmin();

        $this->actingAs($otherSuperAdmin);

        $this->put(route('admin.admins.update', $root), [...$this->staffPayload($root->username), 'status' => 'suspended'])->assertForbidden();
        $this->delete(route('admin.admins.destroy', $root))->assertForbidden();
        $this->put(route('super.permissions.update', $root), ['permissions' => []])->assertForbidden();

        $this->assertTrue($root->fresh()->isSuperAdmin());
        $this->assertFalse($root->fresh()->isSuspended());
    }

    public function test_staff_cannot_change_their_own_access(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->put(route('admin.admins.update', $superAdmin), $this->staffPayload($superAdmin->username))
            ->assertForbidden();
    }

    public function test_super_admin_edits_suspends_and_removes_admins(): void
    {
        $admin = $this->adminWith([AdminPermission::ManageUsers]);

        $this->actingAs($this->superAdmin())
            ->put(route('admin.admins.update', $admin), [
                ...$this->staffPayload($admin->username, ['finance.view', 'contracts.manage']),
                'name' => 'نام جدید',
                'email' => $admin->email,
                'status' => 'suspended',
            ])
            ->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('نام جدید', $admin->name);
        $this->assertTrue($admin->isSuspended());
        $this->assertEqualsCanonicalizing(['finance.view', 'contracts.manage'], $admin->staffPermissions());

        // A suspended admin is signed out on the next request.
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($this->superAdmin())->delete(route('admin.admins.destroy', $admin))->assertRedirect();
        $this->assertSoftDeleted($admin);
    }

    public function test_the_admins_routes_do_not_touch_marketplace_members(): void
    {
        $freelancer = $this->freelancer();

        $this->actingAs($this->superAdmin())
            ->put(route('admin.admins.update', $freelancer), $this->staffPayload($freelancer->username))
            ->assertNotFound();
    }

    public function test_permission_matrix_toggles_one_admin_at_a_time(): void
    {
        $admin = $this->adminWith([AdminPermission::ManageUsers]);

        $this->actingAs($this->superAdmin())
            ->get(route('super.permissions.index'))
            ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Permissions')->has('staff', 2)->has('permissions', count(AdminPermission::cases())));

        $this->put(route('super.permissions.update', $admin), ['permissions' => ['users.manage', 'settings.manage']])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'دسترسی‌های '.$admin->name.' به‌روز شد.');

        $this->assertEqualsCanonicalizing(['users.manage', 'settings.manage'], $admin->fresh()->staffPermissions());

        $this->put(route('super.permissions.update', $admin), ['permissions' => ['not.a.permission']])->assertSessionHasErrors('permissions.0');
        $this->put(route('super.permissions.update', $this->freelancer()), ['permissions' => []])->assertNotFound();
    }

    public function test_only_super_admins_open_the_developer_panel(): void
    {
        $this->actingAs($this->adminWith(AdminPermission::cases()))->get(route('super.permissions.index'))->assertForbidden();
    }
}
