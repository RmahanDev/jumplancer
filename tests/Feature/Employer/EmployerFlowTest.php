<?php

namespace Tests\Feature\Employer;

use App\Enums\BudgetType;
use App\Enums\ContractStatus;
use App\Enums\ExperienceLevel;
use App\Enums\MilestoneStatus;
use App\Enums\PostingType;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TicketStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\CategoryBudgetRange;
use App\Models\Contract;
use App\Models\Milestone;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class EmployerFlowTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(array $overrides = []): array
    {
        return [
            'title' => 'طراحی سایت شرکتی با وردپرس',
            'description' => 'یک سایت پنج صفحه‌ای شرکتی با وردپرس، واکنش‌گرا و سریع، به همراه آموزش کار با پنل.',
            'category_id' => $this->category('web-development')->id,
            'budget_type' => 'fixed',
            'budget_min' => 5_000_000,
            'budget_max' => 8_000_000,
            'deadline' => now()->addMonth()->toDateString(),
            'is_beginner_friendly' => true,
            'skills' => [],
            'submit' => true,
            ...$overrides,
        ];
    }

    public function test_the_first_two_projects_are_free_and_then_a_plan_is_needed(): void
    {
        $employer = $this->employer();
        $this->actingAs($employer);

        $this->post(route('employer.projects.store'), $this->projectPayload(['title' => 'پروژه‌ی اول رایگان']))->assertSessionHasNoErrors();
        $first = Project::firstWhere('title', 'پروژه‌ی اول رایگان');
        $this->assertSame(ProjectStatus::PendingReview, $first->status);
        $this->assertSame(PostingType::FreeFirst, $first->posting_type);

        $profile = $employer->employerProfile()->first();
        $this->assertSame(1, $profile->free_projects_used);
        $this->assertTrue($profile->second_free_until->between(now()->addDays(29), now()->addDays(31)));

        $this->post(route('employer.projects.store'), $this->projectPayload(['title' => 'پروژه‌ی دوم رایگان']))->assertSessionHasNoErrors();
        $this->assertSame(PostingType::FreeSecond, Project::firstWhere('title', 'پروژه‌ی دوم رایگان')->posting_type);

        $this->post(route('employer.projects.store'), $this->projectPayload(['title' => 'پروژه‌ی سوم']))
            ->assertSessionHasErrors(['project' => 'پروژه‌های رایگانت تمام شده است. برای انتشار پروژه‌ی بیشتر یک پلن بخر.']);
        $this->assertDatabaseMissing('projects', ['title' => 'پروژه‌ی سوم']);

        // Drafts are always allowed.
        $this->post(route('employer.projects.store'), $this->projectPayload(['title' => 'پیش‌نویس', 'submit' => false]))->assertSessionHasNoErrors();
        $this->assertSame(ProjectStatus::Draft, Project::firstWhere('title', 'پیش‌نویس')->status);

        $this->get(route('employer.projects.index'))->assertInertia(fn (Assert $page) => $page->where('posting.next', null));
    }

    public function test_the_second_free_posting_expires_after_its_window(): void
    {
        $employer = $this->employer();
        $this->actingAs($employer)->post(route('employer.projects.store'), $this->projectPayload())->assertSessionHasNoErrors();

        $this->travel(31)->days();

        $this->post(route('employer.projects.store'), $this->projectPayload(['title' => 'پروژه‌ای که دیر ثبت شد']))->assertSessionHasErrors('project');
    }

    public function test_a_plan_bought_from_the_wallet_unlocks_more_projects(): void
    {
        $employer = $this->employer(balance: 2_000_000);
        $employer->employerProfile()->first()->forceFill(['free_projects_used' => 2])->save();
        $plan = Plan::create(['name' => 'پلن رشد', 'price' => 1_290_000, 'project_quota' => 1, 'duration_days' => 30, 'is_active' => true]);
        $expensive = Plan::create(['name' => 'پلن حرفه‌ای', 'price' => 9_000_000, 'project_quota' => null, 'is_active' => true]);
        $retired = Plan::create(['name' => 'قدیمی', 'price' => 1_000, 'is_active' => false]);
        $this->actingAs($employer);

        $this->post(route('employer.plans.subscribe', $expensive))
            ->assertSessionHasErrors(['plan' => 'موجودی کیف پولت کافی نیست. اول ۷٬۰۰۰٬۰۰۰ تومان شارژ کن.']);
        $this->post(route('employer.plans.subscribe', $retired))->assertNotFound();

        $this->post(route('employer.plans.subscribe', $plan))->assertSessionHasNoErrors();
        $subscription = $employer->subscriptions()->sole();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(710_000, $employer->wallet()->first()->balance);
        $this->assertTrue($employer->wallet()->first()->transactions()->where('type', TransactionType::PlanPurchase)->where('amount', -1_290_000)->exists());

        $this->post(route('employer.projects.store'), $this->projectPayload())->assertSessionHasNoErrors();
        $this->assertSame(PostingType::Subscription, Project::sole()->posting_type);
        $this->assertSame(1, $subscription->fresh()->projects_used);

        $this->post(route('employer.projects.store'), $this->projectPayload(['title' => 'سهمیه تمام شد']))->assertSessionHasErrors('project');
    }

    public function test_budgets_follow_the_category_range(): void
    {
        CategoryBudgetRange::create(['category_id' => $this->category('web-development')->id, 'budget_type' => BudgetType::Fixed, 'min_amount' => 3_000_000, 'max_amount' => 50_000_000, 'is_active' => true]);
        CategoryBudgetRange::create(['category_id' => $this->category('programming-tech')->id, 'budget_type' => BudgetType::Hourly, 'min_amount' => 200_000, 'is_active' => true]);
        $this->actingAs($this->employer());

        $this->post(route('employer.projects.store'), $this->projectPayload(['budget_min' => 1_000_000, 'budget_max' => 2_000_000]))
            ->assertSessionHasErrors(['budget_min' => 'حداقل بودجه‌ی این دسته ۳٬۰۰۰٬۰۰۰ تومان است.']);
        $this->post(route('employer.projects.store'), $this->projectPayload(['budget_min' => 10_000_000, 'budget_max' => 60_000_000]))
            ->assertSessionHasErrors(['budget_max' => 'حداکثر بودجه‌ی این دسته ۵۰٬۰۰۰٬۰۰۰ تومان است.']);

        // Hourly rates fall back to the parent category's range.
        $this->post(route('employer.projects.store'), $this->projectPayload(['budget_type' => 'hourly', 'budget_min' => 150_000, 'budget_max' => null]))
            ->assertSessionHasErrors('budget_min');

        $this->post(route('employer.projects.store'), $this->projectPayload(['category_id' => $this->category('programming-tech')->id]))
            ->assertSessionHasErrors(['category_id' => 'برای پروژه یک زیردسته انتخاب کن.']);
        $this->post(route('employer.projects.store'), $this->projectPayload(['deadline' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('deadline');
    }

    public function test_projects_are_edited_while_draft_or_open_and_published_through_review(): void
    {
        $employer = $this->employer();
        $this->actingAs($employer);
        $this->post(route('employer.projects.store'), $this->projectPayload(['submit' => false]))->assertSessionHasNoErrors();
        $draft = Project::sole();

        $this->put(route('employer.projects.update', $draft), $this->projectPayload(['title' => 'عنوان بهتر برای پروژه', 'submit' => false]))->assertSessionHasNoErrors();
        $this->post(route('employer.projects.publish', $draft))->assertSessionHasNoErrors();
        $this->assertSame(ProjectStatus::PendingReview, $draft->fresh()->status);

        $this->put(route('employer.projects.update', $draft), $this->projectPayload())
            ->assertSessionHasErrors(['title' => 'این پروژه در وضعیت فعلی قابل ویرایش نیست.']);

        // Returned by the reviewer: fixing and re-sending does not use another free posting.
        $draft->refresh()->forceFill(['status' => ProjectStatus::Draft, 'review_note' => 'دقیق‌تر بنویس'])->save();
        $this->put(route('employer.projects.update', $draft), $this->projectPayload(['title' => 'نسخه‌ی اصلاح‌شده‌ی پروژه']))->assertSessionHasNoErrors();
        $this->assertSame(ProjectStatus::PendingReview, $draft->fresh()->status);
        $this->assertNull($draft->fresh()->review_note);
        $this->assertSame(1, $employer->employerProfile()->first()->free_projects_used);
    }

    public function test_deleting_drafts_cancelling_live_projects_and_protecting_running_ones(): void
    {
        $employer = $this->employer();
        $draft = $this->openProject($employer, ['status' => ProjectStatus::Draft, 'published_at' => null]);
        $open = $this->openProject($employer);
        $running = $this->openProject($employer, ['status' => ProjectStatus::InProgress]);
        $this->actingAs($employer);

        $this->delete(route('employer.projects.destroy', $draft))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($draft);

        $this->delete(route('employer.projects.destroy', $open))->assertSessionHasNoErrors();
        $this->assertSame(ProjectStatus::Cancelled, $open->fresh()->status);

        $this->delete(route('employer.projects.destroy', $running))->assertSessionHasErrors('project');
    }

    public function test_employers_only_touch_their_own_projects_and_proposals(): void
    {
        $owner = $this->employer();
        $project = $this->openProject($owner);
        $proposal = Proposal::factory()->create(['project_id' => $project->id, 'freelancer_id' => $this->freelancer()->id]);
        $intruder = $this->employer();

        $this->actingAs($intruder);
        $this->put(route('employer.projects.update', $project), $this->projectPayload())->assertNotFound();
        $this->delete(route('employer.projects.destroy', $project))->assertNotFound();
        $this->put(route('employer.proposals.update', $proposal), ['status' => 'shortlisted'])->assertNotFound();
        $this->post(route('employer.contracts.store', $proposal))->assertNotFound();

        $this->get(route('employer.proposals.index'))->assertInertia(fn (Assert $page) => $page->has('proposals.data', 0));
        $this->get(route('employer.projects.index'))->assertInertia(fn (Assert $page) => $page->has('projects.data', 0));
    }

    public function test_shortlisting_and_rejecting_proposals(): void
    {
        $employer = $this->employer();
        $proposal = Proposal::factory()->create(['project_id' => $this->openProject($employer)->id, 'freelancer_id' => $this->freelancer()->id]);
        $this->actingAs($employer);

        $this->put(route('employer.proposals.update', $proposal), ['status' => 'shortlisted'])->assertInertiaFlash('toast.message', 'به فهرست کوتاه اضافه شد.');
        $this->assertSame(ProposalStatus::Shortlisted, $proposal->fresh()->status);

        $this->put(route('employer.proposals.update', $proposal), ['status' => 'accepted'])->assertSessionHasErrors('status');
        $this->put(route('employer.proposals.update', $proposal), ['status' => 'rejected'])->assertSessionHasNoErrors();
        $this->put(route('employer.proposals.update', $proposal), ['status' => 'pending'])
            ->assertSessionHasErrors(['status' => 'درباره‌ی این پیشنهاد قبلاً تصمیم گرفته شده است.']);
    }

    public function test_hiring_holds_the_deposit_creates_the_contract_and_closes_the_project(): void
    {
        $employer = $this->employer(balance: 10_000_000);
        $project = $this->openProject($employer);
        $hired = $this->freelancer(ExperienceLevel::Intermediate);
        $hired->update(['name' => 'نیما رحیمی']);
        $chosen = Proposal::factory()->create(['project_id' => $project->id, 'freelancer_id' => $hired->id, 'proposed_price' => 7_000_000]);
        $other = Proposal::factory()->create(['project_id' => $project->id, 'freelancer_id' => $this->freelancer()->id]);
        $this->actingAs($employer);

        $this->post(route('employer.contracts.store', $chosen))
            ->assertSessionHasErrors(['accept_deposit_terms' => 'برای استخدام، شرایط امانت حسن انجام کار را تأیید کن.']);

        $this->post(route('employer.contracts.store', $chosen), ['accept_deposit_terms' => true])
            ->assertRedirect(route('employer.contracts.index'))
            ->assertInertiaFlash('toast.message', 'نیما رحیمی را استخدام کردی. ۳٬۱۵۰٬۰۰۰ تومان به‌عنوان امانت حسن انجام کار نگه داشته شد؛ اولین مرحله‌ها از همین مبلغ تأمین می‌شوند.');

        $contract = Contract::sole();
        $this->assertSame(7_000_000, $contract->amount);
        $this->assertSame(20, $contract->fee_percent, 'no mentor requested on the proposal');
        $this->assertFalse($contract->mentorship_included);
        $this->assertSame(3_150_000, $contract->deposit_amount, '45% of the proposal price');
        $this->assertSame(3_150_000, $contract->deposit_balance);

        $wallet = $employer->wallet()->first();
        $this->assertSame(6_850_000, $wallet->balance);
        $this->assertSame(3_150_000, $wallet->held_balance);
        $this->assertSame(-3_150_000, (int) $wallet->transactions()->where('type', TransactionType::EscrowHold)->sole()->amount);

        $this->assertSame(ProposalStatus::Accepted, $chosen->fresh()->status);
        $this->assertSame(ProposalStatus::Rejected, $other->fresh()->status);
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
        $this->assertSame(0, Ticket::count(), 'no mentoring ticket without a request');

        $this->post(route('employer.contracts.store', $other), ['accept_deposit_terms' => true])->assertSessionHasErrors('proposal');
    }

    public function test_hiring_needs_the_deposit_in_the_wallet(): void
    {
        $employer = $this->employer(balance: 1_000_000);
        $proposal = Proposal::factory()->create(['project_id' => $this->openProject($employer)->id, 'freelancer_id' => $this->freelancer()->id, 'proposed_price' => 5_000_000]);

        $this->actingAs($employer)
            ->post(route('employer.contracts.store', $proposal), ['accept_deposit_terms' => true])
            ->assertSessionHasErrors(['deposit' => 'برای استخدام باید ۲٬۲۵۰٬۰۰۰ تومان امانت حسن انجام کار در کیف پولت باشد. اول ۱٬۲۵۰٬۰۰۰ تومان شارژ کن.']);

        $this->assertSame(0, Contract::count());
        $this->assertSame(ProposalStatus::Pending, $proposal->fresh()->status);
        $this->assertSame(1_000_000, $employer->wallet()->first()->balance);
    }

    public function test_the_deposit_percent_comes_from_the_platform_settings(): void
    {
        PlatformSetting::where('setting_key', 'hire_deposit_percent')->update(['setting_value' => '30']);
        $employer = $this->employer(balance: 5_000_000);

        $contract = $this->hire($employer, $this->freelancer(), 2_000_000);

        $this->assertSame(600_000, $contract->deposit_amount);
    }

    public function test_mentoring_comes_from_the_freelancers_proposal_and_opens_a_mentor_ticket(): void
    {
        $employer = $this->employer(balance: 10_000_000);
        $freelancer = $this->freelancer(ExperienceLevel::Intermediate);
        $proposal = Proposal::factory()->create([
            'project_id' => $this->openProject($employer)->id,
            'freelancer_id' => $freelancer->id,
            'proposed_price' => 4_000_000,
            'mentorship_requested' => true,
        ]);

        // The employer cannot switch mentoring on or off.
        $this->actingAs($employer)
            ->post(route('employer.contracts.store', $proposal), ['accept_deposit_terms' => true, 'mentorship_included' => false])
            ->assertSessionHasNoErrors();

        $contract = Contract::sole();
        $this->assertTrue($contract->mentorship_included);
        $this->assertSame(25, $contract->fee_percent, 'paid mentorship adds 5% to the platform fee');
        $this->assertNull($contract->mentor_id, 'a mentor joins when they take the ticket');

        $ticket = Ticket::sole();
        $this->assertSame($contract->id, $ticket->contract_id);
        $this->assertSame($freelancer->id, $ticket->requester_id);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->assigned_mentor_id);
    }

    public function test_beginners_get_their_first_mentorships_free(): void
    {
        $employer = $this->employer(balance: 20_000_000);
        $beginner = $this->freelancer(ExperienceLevel::Beginner);
        $this->actingAs($employer);

        foreach ([1, 2, 3] as $round) {
            $proposal = Proposal::factory()->create(['project_id' => $this->openProject($employer)->id, 'freelancer_id' => $beginner->id, 'mentorship_requested' => true]);
            $this->post(route('employer.contracts.store', $proposal), ['accept_deposit_terms' => true])->assertSessionHasNoErrors();
        }

        $contracts = Contract::orderBy('id')->get();
        $this->assertSame([true, true, false], $contracts->pluck('is_free_mentorship')->all());
        $this->assertSame([20, 20, 25], $contracts->pluck('fee_percent')->all());
        $this->assertSame(2, $beginner->freelancerProfile()->first()->free_mentorships_used);
        $this->assertSame(3, Ticket::whereNotNull('contract_id')->count());
    }

    public function test_milestones_spend_the_deposit_first_then_the_wallet_and_release_with_the_fee(): void
    {
        $employer = $this->employer(balance: 4_000_000);
        $freelancer = $this->freelancer();
        $contract = $this->hire($employer, $freelancer, 5_000_000);
        $this->actingAs($employer);

        // Hiring held 45% (2,250,000); 1,750,000 is still available.
        $wallet = $employer->wallet()->first();
        $this->assertSame([1_750_000, 2_250_000], [$wallet->balance, $wallet->held_balance]);

        $this->post(route('employer.milestones.store', $contract), ['title' => 'بیش از قرارداد', 'amount' => 6_000_000])
            ->assertSessionHasErrors(['amount' => 'جمع مرحله‌ها نمی‌تواند از مبلغ قرارداد بیشتر شود. ۵٬۰۰۰٬۰۰۰ تومان برای برنامه‌ریزی باقی مانده است.']);

        $this->post(route('employer.milestones.store', $contract), ['title' => 'طراحی قالب', 'amount' => 3_000_000, 'due_date' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        $this->post(route('employer.milestones.store', $contract), ['title' => 'انتشار', 'amount' => 2_000_000])->assertSessionHasNoErrors();
        [$first, $second] = Milestone::orderBy('id')->get();

        $this->put(route('employer.milestones.update', $first), ['action' => 'release'])
            ->assertSessionHasErrors(['milestone' => 'فقط مرحله‌ی تحویل‌شده قابل پرداخت است.']);

        // 2,250,000 from the deposit + 750,000 from the wallet.
        $this->put(route('employer.milestones.update', $first), ['action' => 'fund'])->assertSessionHasNoErrors();
        $wallet->refresh();
        $this->assertSame(1_000_000, $wallet->balance);
        $this->assertSame(3_000_000, $wallet->held_balance);
        $this->assertSame(0, $contract->fresh()->deposit_balance);

        $this->put(route('employer.milestones.update', $second), ['action' => 'fund'])
            ->assertSessionHasErrors(['milestone' => 'موجودی کیف پولت کافی نیست. اول ۱٬۰۰۰٬۰۰۰ تومان شارژ کن.']);
        $this->put(route('employer.contracts.update', $contract), ['status' => 'completed'])
            ->assertSessionHasErrors(['status' => 'قبل از بستن قرارداد، مرحله‌های تأمین‌شده را پرداخت یا تسویه کن.']);

        $this->actingAs($freelancer)->post(route('freelancer.milestones.submit', $first))->assertSessionHasNoErrors();

        $this->actingAs($employer)->put(route('employer.milestones.update', $first), ['action' => 'release'])->assertSessionHasNoErrors();
        $this->assertSame(MilestoneStatus::Released, $first->fresh()->status);
        $this->assertSame(0, $employer->wallet()->first()->held_balance);

        $freelancerWallet = $freelancer->wallet()->first();
        $this->assertSame(2_400_000, $freelancerWallet->balance, '3,000,000 minus the 20% platform fee');
        $this->assertSame(
            [3_000_000, -600_000],
            $freelancerWallet->transactions()->orderBy('id')->pluck('amount')->all(),
        );
        $this->assertLedgerMatchesWallets();
    }

    public function test_the_unused_deposit_returns_when_the_contract_completes(): void
    {
        $employer = $this->employer(balance: 10_000_000);
        $contract = $this->hire($employer, $this->freelancer(), 4_000_000);
        $this->actingAs($employer);

        $this->put(route('employer.contracts.update', $contract), ['status' => 'completed'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'قرارداد تکمیل شد و ۱٬۸۰۰٬۰۰۰ تومان امانت مصرف‌نشده به کیف پولت برگشت. برای فریلنسر نظر بگذار!');

        $wallet = $employer->wallet()->first();
        $this->assertSame([10_000_000, 0], [$wallet->balance, $wallet->held_balance]);
        $this->assertSame(0, $contract->fresh()->deposit_balance);
        $this->assertLedgerMatchesWallets();
    }

    public function test_an_employer_cannot_cancel_while_the_deposit_is_held(): void
    {
        $employer = $this->employer(balance: 10_000_000);
        $contract = $this->hire($employer, $this->freelancer(), 4_000_000);
        $this->actingAs($employer);

        $this->put(route('employer.contracts.update', $contract), ['status' => 'cancelled'])
            ->assertSessionHasErrors(['status' => 'امانت حسن انجام کار تا تصمیم کارشناس نگه داشته می‌شود. درخواست بررسی کارشناس ثبت کن و دلیل لغو را بنویس.']);

        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
        $this->assertSame(1_800_000, $employer->wallet()->first()->held_balance);
    }

    public function test_completed_contracts_are_reviewed_once(): void
    {
        $employer = $this->employer(balance: 1_000_000);
        $freelancer = $this->freelancer();
        $contract = $this->hire($employer, $freelancer, 1_000_000);
        $this->actingAs($employer);

        $this->post(route('employer.reviews.store', $contract), ['rating' => 5])
            ->assertSessionHasErrors(['rating' => 'بعد از تکمیل قرارداد می‌توانی برای فریلنسر نظر ثبت کنی.']);

        $this->put(route('employer.contracts.update', $contract), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame(ContractStatus::Completed, $contract->fresh()->status);
        $this->assertSame(ProjectStatus::Completed, $contract->project()->first()->status);

        $this->post(route('employer.reviews.store', $contract), ['rating' => 6])->assertSessionHasErrors('rating');
        $this->post(route('employer.reviews.store', $contract), ['rating' => 5, 'comment' => 'عالی بود'])->assertSessionHasNoErrors();
        $this->post(route('employer.reviews.store', $contract), ['rating' => 4])->assertSessionHasErrors(['rating' => 'برای این قرارداد قبلاً نظر ثبت کرده‌ای.']);

        $this->assertSame(5, $freelancer->reviewsReceived()->sole()->rating);
        $this->get(route('employer.contracts.index'))->assertInertia(fn (Assert $page) => $page->where('contracts.data.0.reviewed', true));
    }

    public function test_milestones_of_other_employers_are_invisible(): void
    {
        $contract = $this->hire($this->employer(balance: 1_000_000), $this->freelancer(), 1_000_000);
        $milestone = Milestone::factory()->create(['contract_id' => $contract->id]);

        $this->actingAs($this->employer(balance: 5_000_000));
        $this->put(route('employer.milestones.update', $milestone), ['action' => 'fund'])->assertNotFound();
        $this->post(route('employer.milestones.store', $contract), ['title' => 'x', 'amount' => 1_000])->assertNotFound();
        $this->put(route('employer.contracts.update', $contract), ['status' => 'cancelled'])->assertNotFound();
    }

    private function hire(User $employer, User $freelancer, int $amount): Contract
    {
        $proposal = Proposal::factory()->create([
            'project_id' => $this->openProject($employer)->id,
            'freelancer_id' => $freelancer->id,
            'proposed_price' => $amount,
        ]);

        $this->actingAs($employer)->post(route('employer.contracts.store', $proposal), ['accept_deposit_terms' => true])->assertSessionHasNoErrors();

        return Contract::where('proposal_id', $proposal->id)->sole();
    }

    private function assertLedgerMatchesWallets(): void
    {
        foreach (Wallet::all() as $wallet) {
            $this->assertSame(
                $wallet->balance,
                (int) $wallet->transactions()->whereIn('status', [TransactionStatus::Succeeded, TransactionStatus::Pending])->sum('amount'),
            );
        }
    }
}
