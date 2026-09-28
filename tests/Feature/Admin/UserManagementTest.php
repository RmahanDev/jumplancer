<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->admin = $this->adminWith([AdminPermission::ManageUsers]);
    }

    public function test_admin_creates_a_member_with_roles_profiles_and_wallet(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'سحر ملکی',
                'username' => 'sahar',
                'email' => 'sahar@example.com',
                'phone' => '09121112233',
                'password' => 'secret123',
                'roles' => ['freelancer', 'mentor'],
            ])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'حساب سحر ملکی ساخته شد.');

        $member = User::with(['freelancerProfile', 'mentorProfile', 'wallet'])->firstWhere('username', 'sahar');

        $this->assertTrue($member->hasAllRoles([RoleName::Freelancer, RoleName::Mentor]));
        $this->assertNotNull($member->freelancerProfile);
        $this->assertTrue($member->mentorProfile->is_verified, 'mentors added by staff are verified');
        $this->assertNotNull($member->wallet);
        $this->assertNotNull($member->email_verified_at);
    }

    public function test_member_validation_speaks_persian(): void
    {
        $existing = $this->freelancer();

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => '',
                'username' => $existing->username,
                'email' => 'not-an-email',
                'phone' => '12345',
                'password' => 'short',
                'roles' => ['admin'],
            ])
            ->assertSessionHasErrors(['name', 'username', 'email', 'phone', 'password', 'roles.0']);

        $errors = session('errors');
        $this->assertSame('وارد کردن نام الزامی است.', $errors->first('name'));
        $this->assertStringContainsString('قبلاً', $errors->first('username'));
    }

    public function test_admin_updates_roles_and_keeps_the_password_when_left_blank(): void
    {
        $member = $this->freelancer();
        $passwordHash = $member->password;

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $member), [
                'name' => $member->name,
                'username' => $member->username,
                'email' => $member->email,
                'password' => '',
                'roles' => ['freelancer', 'employer'],
            ])
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertTrue($member->hasRole(RoleName::Employer));
        $this->assertNotNull($member->employerProfile()->first());
        $this->assertSame($passwordHash, $member->password);
    }

    public function test_suspending_needs_a_reason_and_signs_the_member_out(): void
    {
        $member = $this->freelancer();

        $this->actingAs($this->admin)
            ->put(route('admin.users.status', $member), ['status' => 'suspended'])
            ->assertSessionHasErrors('reason');

        $this->put(route('admin.users.status', $member), ['status' => 'suspended', 'reason' => 'اشتراک شماره‌ی تماس'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'warning');

        $member->refresh();
        $this->assertSame(UserStatus::Suspended, $member->status);
        $this->assertSame('اشتراک شماره‌ی تماس', $member->suspension_reason);

        $this->actingAs($member)->get(route('freelancer.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($this->admin)->put(route('admin.users.status', $member), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->isSuspended());
        $this->assertNull($member->fresh()->suspension_reason);
    }

    public function test_deleting_a_member_keeps_their_history(): void
    {
        $member = $this->employer();
        $project = $this->openProject($member);

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $member))->assertRedirect();

        $this->assertSoftDeleted($member);
        $this->assertModelExists($project);
    }

    public function test_staff_accounts_are_not_managed_from_the_users_page(): void
    {
        $otherAdmin = $this->adminWith();

        $this->actingAs($this->admin)
            ->put(route('admin.users.status', $otherAdmin), ['status' => 'suspended', 'reason' => 'test'])
            ->assertForbidden();

        $this->delete(route('admin.users.destroy', $this->admin))->assertForbidden();

        $this->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page->where('users.data', fn ($users) => collect($users)->pluck('username')->doesntContain($otherAdmin->username)));
    }

    public function test_users_page_filters_by_role_status_and_persian_search(): void
    {
        $sara = $this->freelancer();
        $sara->update(['name' => 'سارا کیانی']);
        $employer = $this->employer();
        $suspended = $this->freelancer();
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();

        $this->actingAs($this->admin);

        // Arabic "ي/ك" typed on some keyboards still finds the Persian name.
        $this->get(route('admin.users.index', ['search' => 'كياني']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.username', $sara->username));

        $this->get(route('admin.users.index', ['role' => 'employer']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.username', $employer->username));

        $this->get(route('admin.users.index', ['status' => 'suspended']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('filters.status', 'suspended'));

        $this->get(route('admin.users.index', ['role' => 'admin']))->assertSessionHasErrors('role');
    }

    public function test_super_admin_browses_as_a_member_and_comes_back(): void
    {
        $superAdmin = $this->superAdmin();
        $member = $this->freelancer();

        $this->actingAs($superAdmin)
            ->post(route('super.impersonate', $member))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($member);

        $this->get(route('freelancer.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('impersonating.name', $member->name));

        $this->delete(route('impersonation.destroy'))->assertRedirect(route('super.dashboard'));
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_impersonation_is_for_super_admins_and_never_targets_another_super_admin(): void
    {
        $member = $this->freelancer();

        $this->actingAs($this->adminWith(AdminPermission::cases()))->post(route('super.impersonate', $member))->assertForbidden();
        $this->actingAs($this->superAdmin())->post(route('super.impersonate', $this->superAdmin()))->assertForbidden();
        $this->actingAs($member)->delete(route('impersonation.destroy'))->assertForbidden();
    }
}
