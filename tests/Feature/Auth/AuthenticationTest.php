<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_login_page_is_persian_and_right_to_left(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('lang="fa"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('خوش برگشتی');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function identifiers(): array
    {
        return [
            'username' => ['username'],
            'email' => ['email'],
            'mobile typed with Persian digits' => ['mobile'],
        ];
    }

    #[DataProvider('identifiers')]
    public function test_members_sign_in_with_username_email_or_mobile(string $kind): void
    {
        $user = $this->freelancer();
        $user->update(['phone' => '09121234567']);

        $identifier = match ($kind) {
            'username' => $user->username,
            'email' => $user->email,
            'mobile' => '۰۹۱۲۱۲۳۴۵۶۷',
        };

        $this->post(route('login.store'), ['identifier' => $identifier, 'password' => 'password'])
            ->assertRedirect(route('dashboard'))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_a_wrong_password_is_rejected_with_a_persian_message(): void
    {
        $user = $this->freelancer();

        $this->from(route('login'))
            ->post(route('login.store'), ['identifier' => $user->username, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['identifier' => __('auth.failed')]);

        $this->assertGuest();
        $this->assertSame('نام کاربری یا رمز عبور درست نیست.', __('auth.failed', [], 'fa'));
    }

    public function test_suspended_members_cannot_sign_in(): void
    {
        $user = User::factory()->suspended()->create(['username' => 'blocked']);

        $this->post(route('login.store'), ['identifier' => 'blocked', 'password' => 'password'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_a_member_suspended_while_signed_in_is_signed_out_on_the_next_request(): void
    {
        $user = $this->freelancer();
        $this->actingAs($user);
        $user->forceFill(['status' => 'suspended'])->save();

        $this->get(route('freelancer.dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_dashboard_sends_each_role_to_its_own_panel(): void
    {
        $this->actingAs($this->superAdmin())->get(route('dashboard'))->assertRedirect(route('super.dashboard'));
        $this->actingAs($this->adminWith())->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->mentor())->get(route('dashboard'))->assertRedirect(route('mentor.dashboard'));
        $this->actingAs($this->freelancer())->get(route('dashboard'))->assertRedirect(route('freelancer.dashboard'));
        $this->actingAs($this->employer())->get(route('dashboard'))->assertRedirect(route('employer.dashboard'));
    }

    public function test_logout_is_a_full_page_redirect_to_the_blade_login(): void
    {
        $this->actingAs($this->freelancer());

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Inertia visits get a 409 with the location header instead of a redirect.
        $this->actingAs($this->freelancer());
        $this->post(route('logout'), [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('freelancer.projects.index'))->assertRedirect(route('login'));
    }

    public function test_visitors_register_as_freelancer_or_employer_with_profile_and_wallet(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('dir="rtl"', false);

        $this->post(route('register.store'), [
            'name' => 'مینا  رستمی',
            'username' => 'Mina_R',
            'email' => 'MINA@example.com',
            'phone' => '۰۹۳۵۱۱۱۲۲۳۳',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => RoleName::Freelancer->value,
        ])->assertRedirect(route('dashboard'));

        $user = User::with(['freelancerProfile', 'wallet'])->firstWhere('username', 'mina_r');

        $this->assertNotNull($user);
        $this->assertSame('mina@example.com', $user->email);
        $this->assertSame('09351112233', $user->phone);
        $this->assertTrue($user->hasRole(RoleName::Freelancer));
        $this->assertNotNull($user->freelancerProfile);
        $this->assertNotNull($user->wallet);
        $this->assertAuthenticatedAs($user);
    }

    public function test_nobody_can_register_as_staff(): void
    {
        foreach ([RoleName::Admin, RoleName::SuperAdmin, RoleName::Mentor] as $role) {
            $this->post(route('register.store'), [
                'name' => 'نفوذی',
                'username' => 'intruder',
                'email' => 'intruder@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'role' => $role->value,
            ])->assertSessionHasErrors('role');
        }

        $this->assertDatabaseMissing('users', ['username' => 'intruder']);
    }

    public function test_registration_validates_in_persian(): void
    {
        app()->setLocale('fa');

        $this->post(route('register.store'), ['role' => 'freelancer'])
            ->assertSessionHasErrors(['name', 'username', 'email', 'password']);

        $this->assertStringContainsString('الزامی', session('errors')->first('name'));
    }
}
