<?php

namespace Tests\Feature\Shared;

use App\Enums\Availability;
use App\Enums\CompanySize;
use App\Enums\MentoringStyle;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Enums\TransactionType;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * Pages every member shares: profile, password, wallet and mentoring requests.
 */
class AccountTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_a_freelancer_saves_the_account_and_the_freelancer_profile_together(): void
    {
        $freelancer = $this->freelancer();
        $this->actingAs($freelancer);

        $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shared/Profile')
                ->where('account.username', $freelancer->username)
                ->has('profiles.freelancer')
                ->missing('profiles.employer')
                ->missing('profiles.mentor'));

        $this->put(route('profile.update'), [
            'name' => 'سارا   احمدی',
            'username' => 'Sara.Dev۲',
            'email' => 'Sara@Example.COM',
            'phone' => '۰۹۱۲ ۳۴۵ ۶۷۸۹',
            'bio' => 'برنامه‌نویس لاراول',
            'freelancer' => ['headline' => 'توسعه‌دهنده‌ی بک‌اند', 'hourly_rate' => 450_000, 'availability' => 'busy'],
        ])->assertSessionHasNoErrors()->assertInertiaFlash('toast.message', 'پروفایلت ذخیره شد.');

        $freelancer->refresh();
        $this->assertSame('سارا احمدی', $freelancer->name);
        $this->assertSame('sara.dev2', $freelancer->username);
        $this->assertSame('sara@example.com', $freelancer->email);
        $this->assertSame('09123456789', $freelancer->phone);

        $profile = $freelancer->freelancerProfile()->first();
        $this->assertSame('توسعه‌دهنده‌ی بک‌اند', $profile->headline);
        $this->assertSame(450_000, $profile->hourly_rate);
        $this->assertSame(Availability::Busy, $profile->availability);
    }

    public function test_profiles_of_roles_the_member_does_not_have_are_refused(): void
    {
        $freelancer = $this->freelancer();
        $taken = $this->employer();
        $this->actingAs($freelancer);

        $account = ['name' => $freelancer->name, 'username' => $freelancer->username, 'email' => $freelancer->email];

        $this->put(route('profile.update'), [...$account, 'employer' => ['company_name' => 'شرکت من']])->assertSessionHasErrors('employer');
        $this->put(route('profile.update'), [...$account, 'username' => $taken->username])->assertSessionHasErrors('username');
        $this->put(route('profile.update'), [...$account, 'username' => 'نام فارسی'])->assertSessionHasErrors('username');
        $this->put(route('profile.update'), [...$account, 'phone' => '12345'])->assertSessionHasErrors('phone');
        $this->put(route('profile.update'), [...$account, 'freelancer' => ['availability' => 'on-vacation']])->assertSessionHasErrors('freelancer.availability');
    }

    public function test_employers_and_mentors_edit_their_own_role_profiles(): void
    {
        $employer = $this->employer();
        $this->actingAs($employer)->put(route('profile.update'), [
            'name' => $employer->name,
            'username' => $employer->username,
            'email' => $employer->email,
            'employer' => ['company_name' => 'فروشگاه نارنج', 'company_size' => '11-50', 'industry' => 'فروشگاه اینترنتی', 'website' => 'https://naranj.example', 'open_to_beginners' => true],
        ])->assertSessionHasNoErrors();

        $company = $employer->employerProfile()->first();
        $this->assertSame('فروشگاه نارنج', $company->company_name);
        $this->assertSame(CompanySize::ElevenToFifty, $company->company_size);
        $this->assertTrue($company->open_to_beginners);

        $mentor = $this->mentor();
        $this->actingAs($mentor);
        $account = ['name' => $mentor->name, 'username' => $mentor->username, 'email' => $mentor->email];

        $this->put(route('profile.update'), [...$account, 'mentor' => ['expertise_summary' => 'لاراول و معماری']])
            ->assertSessionHasErrors(['mentor.years_experience', 'mentor.mentoring_style', 'mentor.max_mentees']);
        $this->put(route('profile.update'), [...$account, 'mentor' => ['expertise_summary' => 'لاراول و معماری', 'years_experience' => 9, 'mentoring_style' => 'both', 'max_mentees' => 6]])
            ->assertSessionHasNoErrors();

        $profile = $mentor->mentorProfile()->first();
        $this->assertSame(9, $profile->years_experience);
        $this->assertSame(MentoringStyle::Both, $profile->mentoring_style);
        $this->assertSame(6, $profile->max_mentees);
        $this->assertTrue($profile->is_verified, 'Members cannot change their own verification.');
    }

    public function test_the_password_is_changed_with_the_current_one(): void
    {
        $this->actingAs($member = $this->freelancer());

        $this->put(route('profile.password.update'), ['current_password' => 'wrong-password', 'password' => 'newSecret123', 'password_confirmation' => 'newSecret123'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('profile.password.update'), ['current_password' => 'password', 'password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])
            ->assertSessionHasErrors('password');
        $this->put(route('profile.password.update'), ['current_password' => 'password', 'password' => 'newSecret123', 'password_confirmation' => 'another123'])
            ->assertSessionHasErrors('password');

        $this->put(route('profile.password.update'), ['current_password' => 'password', 'password' => 'newSecret123', 'password_confirmation' => 'newSecret123'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'رمز عبورت تغییر کرد.');

        $this->assertTrue(Hash::check('newSecret123', $member->fresh()->password));
    }

    public function test_the_sandbox_gateway_charges_the_wallet_and_the_ledger_shows_it(): void
    {
        $this->actingAs($employer = $this->employer());

        $this->post(route('wallet.deposits.store'), ['amount' => 5_000])->assertSessionHasErrors('amount');
        $this->post(route('wallet.deposits.store'), ['amount' => 600_000_000])->assertSessionHasErrors('amount');

        $this->post(route('wallet.deposits.store'), ['amount' => 2_500_000])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'کیف پولت ۲٬۵۰۰٬۰۰۰ تومان شارژ شد.');

        $this->assertSame(2_500_000, $employer->wallet()->first()->balance);

        $this->get(route('wallet.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shared/Wallet')
                ->where('wallet', ['balance' => 2_500_000, 'held_balance' => 0])
                ->where('totals', ['income' => 2_500_000, 'spent' => 0])
                ->has('transactions.data', 1)
                ->where('transactions.data.0.type', TransactionType::Deposit->value)
                ->where('sandbox.enabled', true));

        $this->get(route('wallet.show', ['type' => 'plan_purchase']))->assertInertia(fn (Assert $page) => $page->has('transactions.data', 0));
        $this->get(route('wallet.show', ['type' => 'bitcoin']))->assertSessionHasErrors('type');
    }

    public function test_the_sandbox_gateway_is_closed_when_payments_are_live(): void
    {
        config(['jumplancer.payments.sandbox' => false]);
        $this->actingAs($employer = $this->employer());

        $this->post(route('wallet.deposits.store'), ['amount' => 1_000_000])->assertNotFound();
        $this->assertSame(0, $employer->wallet()->first()->balance);
    }

    public function test_members_ask_for_mentoring_by_ticket_or_phone(): void
    {
        $this->actingAs($freelancer = $this->freelancer());
        Ticket::factory()->create(['requester_id' => $this->employer()->id]);

        $request = ['ticket_type' => 'technical', 'channel' => 'phone', 'subject' => 'اولین قرارداد', 'message' => 'برای نوشتن پیشنهاد اولم کمک می‌خواهم.'];

        $this->post(route('tickets.store'), $request)->assertSessionHasErrors('phone_number');
        $this->post(route('tickets.store'), [...$request, 'message' => 'کمک'])->assertSessionHasErrors('message');
        $this->post(route('tickets.store'), [...$request, 'phone_number' => '+98 912 123 4567'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'درخواستت ثبت شد. به‌زودی یک منتور پیگیری‌اش می‌کند.');

        $ticket = $freelancer->tickets()->sole();
        $this->assertSame('09121234567', $ticket->phone_number);
        $this->assertSame(TicketChannel::Phone, $ticket->channel);
        $this->assertSame(TicketStatus::Open, $ticket->status);

        $this->get(route('tickets.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Shared/Tickets')->has('tickets.data', 1)->where('tickets.data.0.id', $ticket->id));

        // The request lands in the mentors' shared queue.
        $this->actingAs($this->mentor())
            ->get(route('mentor.tickets.index'))
            ->assertInertia(fn (Assert $page) => $page->where('counts.queue', 2));
    }
}
