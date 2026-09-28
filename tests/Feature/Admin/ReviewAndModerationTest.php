<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\ContractStatus;
use App\Enums\DisputeOutcome;
use App\Enums\DisputeStatus;
use App\Enums\FreelancerFieldStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ModerationStatus;
use App\Enums\ProjectStatus;
use App\Enums\TicketStatus;
use App\Enums\UserStatus;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\FreelancerField;
use App\Models\LearningContent;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Violation;
use App\Services\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ReviewAndModerationTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->admin = $this->adminWith(AdminPermission::cases());
        $this->actingAs($this->admin);
    }

    public function test_projects_waiting_for_review_come_first_and_can_be_published(): void
    {
        $employer = $this->employer();
        $this->openProject($employer, ['title' => 'پروژه‌ی باز']);
        $pending = $this->openProject($employer, ['title' => 'منتظر بررسی', 'status' => ProjectStatus::PendingReview, 'published_at' => null]);

        $this->get(route('admin.projects.index'))
            ->assertInertia(fn (Assert $page) => $page->where('projects.data.0.id', $pending->id)->where('statusCounts.pending_review', 1));

        $this->put(route('admin.projects.review', $pending), ['decision' => 'approve'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'پروژه‌ی «منتظر بررسی» منتشر شد.');

        $this->assertSame(ProjectStatus::Open, $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->published_at);

        $this->put(route('admin.projects.review', $pending), ['decision' => 'approve'])->assertSessionHasErrors('decision');
    }

    public function test_returning_a_project_needs_a_note_for_the_employer(): void
    {
        $project = $this->openProject($this->employer(), ['status' => ProjectStatus::PendingReview, 'published_at' => null]);

        $this->put(route('admin.projects.review', $project), ['decision' => 'return'])->assertSessionHasErrors('review_note');
        $this->put(route('admin.projects.review', $project), ['decision' => 'return', 'review_note' => 'شرح کار ناقص است.'])->assertSessionHasNoErrors();

        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
        $this->assertSame('شرح کار ناقص است.', $project->fresh()->review_note);
    }

    public function test_staff_edit_and_soft_delete_projects(): void
    {
        $project = $this->openProject($this->employer());

        $this->put(route('admin.projects.update', $project), [
            'title' => 'عنوان اصلاح‌شده',
            'description' => $project->description,
            'category_id' => $project->category_id,
            'status' => 'open',
            'budget_type' => 'fixed',
            'budget_min' => 1_000_000,
            'budget_max' => 2_000_000,
            'is_beginner_friendly' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('عنوان اصلاح‌شده', $project->fresh()->title);

        // Like employers, staff keep projects in a sub-category.
        $this->put(route('admin.projects.update', $project), [
            'title' => 'x', 'description' => 'y', 'category_id' => $this->category('programming-tech')->id, 'status' => 'open', 'budget_type' => 'fixed',
        ])->assertSessionHasErrors(['category_id' => 'برای پروژه یک زیردسته انتخاب کن.']);

        $this->delete(route('admin.projects.destroy', $project))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($project);
    }

    public function test_the_projects_page_shows_the_hired_freelancer_and_every_proposal(): void
    {
        $contract = $this->contract();
        $project = $contract->project()->first();
        $other = Proposal::factory()->create([
            'project_id' => $project->id,
            'freelancer_id' => $this->freelancer()->id,
            'cover_letter' => 'سلام، این پروژه را با منتور جلو می‌برم.',
            'proposed_price' => 4_200_000,
            'delivery_days' => 12,
            'mentorship_requested' => true,
            'status' => 'rejected',
        ]);
        Proposal::factory()->create(['project_id' => $project->id, 'freelancer_id' => $this->freelancer()->id, 'status' => 'draft']);

        $this->get(route('admin.projects.index'))->assertInertia(fn (Assert $page) => $page
            ->missing('mentors')
            ->where('projects.data.0.freelancer.id', $contract->freelancer_id)
            ->where('projects.data.0.freelancer.roles', ['freelancer'])
            ->where('projects.data.0.employer.roles', ['employer'])
            ->where('projects.data.0.contract.id', $contract->id)
            ->has('projects.data.0.proposals', 2)
            ->where('projects.data.0.proposals.0.id', $contract->proposal_id)
            ->where('projects.data.0.proposals.1.id', $other->id)
            ->where('projects.data.0.proposals.1.cover_letter', 'سلام، این پروژه را با منتور جلو می‌برم.')
            ->where('projects.data.0.proposals.1.proposed_price', 4_200_000)
            ->where('projects.data.0.proposals.1.delivery_days', 12)
            ->where('projects.data.0.proposals.1.mentorship_requested', true));
    }

    public function test_closing_a_dispute_puts_the_contract_back_to_work(): void
    {
        $contract = $this->contract();
        $contract->update(['status' => ContractStatus::Disputed]);
        $dispute = Dispute::factory()->create(['contract_id' => $contract->id, 'raised_by' => $contract->freelancer_id]);

        $this->get(route('admin.contracts.index', ['tab' => 'disputes']))
            ->assertInertia(fn (Assert $page) => $page->where('tab', 'disputes')->has('disputes.data', 1)->where('contracts', null));

        $this->put(route('admin.disputes.update', $dispute), ['status' => 'under_review'])->assertSessionHasNoErrors();
        $this->assertSame(DisputeStatus::UnderReview, $dispute->fresh()->status);

        $this->put(route('admin.disputes.update', $dispute), ['status' => 'resolved'])->assertSessionHasErrors(['resolution_note', 'outcome']);
        $this->put(route('admin.disputes.update', $dispute), ['status' => 'open'])->assertSessionHasErrors('status');

        $this->put(route('admin.disputes.update', $dispute), ['status' => 'resolved', 'outcome' => 'continue', 'resolution_note' => 'دو طرف توافق کردند.'])->assertSessionHasNoErrors();

        $this->assertSame(DisputeStatus::Resolved, $dispute->fresh()->status);
        $this->assertSame(DisputeOutcome::Continue, $dispute->fresh()->outcome);
        $this->assertSame($this->admin->id, $dispute->fresh()->resolved_by);
        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);

        $this->put(route('admin.disputes.update', $dispute), ['status' => 'rejected', 'resolution_note' => 'دوباره'])
            ->assertSessionHasErrors(['status' => 'این اختلاف قبلاً بسته شده است.']);
    }

    public function test_the_expert_can_return_everything_held_to_the_employer(): void
    {
        [$contract, $employer, $freelancer] = $this->heldContract();
        $dispute = $this->raiseDispute($contract, $employer);

        $this->put(route('admin.disputes.update', $dispute), [
            'status' => 'resolved',
            'outcome' => 'refund_employer',
            'resolution_note' => 'کار فریلنسر پایین‌تر از حد انتظار بود.',
        ])->assertSessionHasNoErrors()->assertInertiaFlash('toast.message', 'اختلاف بسته شد و ۵٬۰۰۰٬۰۰۰ تومان به کارفرما برگشت.');

        // 45% deposit (4,500,000): 3,000,000 went into the funded milestone, 1,500,000 is unspent; plus 500,000 more from the wallet.
        $wallet = $employer->wallet()->first();
        $this->assertSame([10_000_000, 0], [$wallet->balance, $wallet->held_balance]);
        $this->assertSame(0, $freelancer->wallet()->first()->balance);
        $this->assertSame(MilestoneStatus::Refunded, $contract->milestones()->first()->status);
        $this->assertSame(ContractStatus::Cancelled, $contract->fresh()->status);
        $this->assertSame(ProjectStatus::Cancelled, $contract->project()->first()->status);
        $this->assertSame(0, $contract->fresh()->deposit_balance);
    }

    public function test_the_expert_can_pay_everything_held_to_the_freelancer(): void
    {
        [$contract, $employer, $freelancer] = $this->heldContract();
        $dispute = $this->raiseDispute($contract, $freelancer);

        $this->put(route('admin.disputes.update', $dispute), [
            'status' => 'resolved',
            'outcome' => 'pay_freelancer',
            'resolution_note' => 'کار درست تحویل شده و کارفرما بدون دلیل پرداخت نمی‌کند.',
        ])->assertSessionHasNoErrors()->assertInertiaFlash('toast.message', 'اختلاف بسته شد و ۵٬۰۰۰٬۰۰۰ تومان به فریلنسر پرداخت شد.');

        $wallet = $employer->wallet()->first();
        $this->assertSame([5_000_000, 0], [$wallet->balance, $wallet->held_balance]);
        $this->assertSame(4_000_000, $freelancer->wallet()->first()->balance, '5,000,000 minus the 20% platform fee');
        $this->assertSame(MilestoneStatus::Released, $contract->milestones()->first()->status);
        $this->assertSame(ContractStatus::Completed, $contract->fresh()->status);
        $this->assertSame(ProjectStatus::Completed, $contract->project()->first()->status);
    }

    public function test_both_sides_can_ask_an_expert_but_outsiders_cannot(): void
    {
        [$contract, $employer, $freelancer] = $this->heldContract();

        $this->actingAs($this->freelancer())
            ->post(route('contracts.disputes.store', $contract), ['reason' => str_repeat('دلیل کافی و روشن ', 3)])
            ->assertNotFound();

        $this->actingAs($freelancer)
            ->post(route('contracts.disputes.store', $contract), ['reason' => 'کوتاه'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($freelancer)
            ->post(route('contracts.disputes.store', $contract), ['reason' => 'کار طبق شرح تحویل شده ولی کارفرما مرحله را تأیید نمی‌کند.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ContractStatus::Disputed, $contract->fresh()->status);
        $this->assertSame(DisputeStatus::Open, Dispute::sole()->status);

        $this->actingAs($employer)
            ->post(route('contracts.disputes.store', $contract), ['reason' => 'یک اختلاف دیگر برای همین قرارداد ثبت می‌کنم.'])
            ->assertSessionHasErrors(['reason' => 'فقط برای قرارداد فعال می‌توانی درخواست بررسی بدهی.']);
    }

    /**
     * A hired contract (price 10,000,000, deposit 4,500,000) with a 5,000,000 milestone funded.
     *
     * @return array{0: Contract, 1: User, 2: User}
     */
    private function heldContract(): array
    {
        $employer = $this->employer(balance: 10_000_000);
        $freelancer = $this->freelancer();
        $proposal = Proposal::factory()->create([
            'project_id' => $this->openProject($employer)->id,
            'freelancer_id' => $freelancer->id,
            'proposed_price' => 10_000_000,
        ]);

        $this->actingAs($employer)->post(route('employer.contracts.store', $proposal), ['accept_deposit_terms' => true])->assertSessionHasNoErrors();
        $contract = Contract::sole();

        $milestone = $contract->milestones()->create(['title' => 'مرحله‌ی اول', 'amount' => 5_000_000, 'status' => MilestoneStatus::Pending, 'sort_order' => 1]);
        app(WalletLedger::class)->fundMilestone($milestone);
        $this->actingAs($this->admin);

        return [$contract->fresh(), $employer, $freelancer];
    }

    private function raiseDispute(Contract $contract, User $by): Dispute
    {
        $this->actingAs($by)
            ->post(route('contracts.disputes.store', $contract), ['reason' => 'طرف مقابل به تعهدش در این قرارداد عمل نکرده است.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin);

        return Dispute::sole();
    }

    public function test_tickets_are_assigned_to_mentors_only(): void
    {
        $ticket = Ticket::factory()->create(['requester_id' => $this->freelancer()->id]);
        $mentor = $this->mentor();

        $this->put(route('admin.tickets.update', $ticket), ['status' => 'assigned', 'assigned_mentor_id' => null])
            ->assertSessionHasErrors(['assigned_mentor_id' => 'برای تیکتِ واگذارشده یک منتور انتخاب کن.']);
        $this->put(route('admin.tickets.update', $ticket), ['status' => 'assigned', 'assigned_mentor_id' => $this->freelancer()->id])
            ->assertSessionHasErrors('assigned_mentor_id');

        $this->put(route('admin.tickets.update', $ticket), ['status' => 'assigned', 'assigned_mentor_id' => $mentor->id])->assertSessionHasNoErrors();
        $this->assertSame($mentor->id, $ticket->fresh()->assigned_mentor_id);

        $this->put(route('admin.tickets.update', $ticket), ['status' => 'closed', 'assigned_mentor_id' => $mentor->id])->assertSessionHasNoErrors();
        $this->assertSame(TicketStatus::Closed, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);

        $this->get(route('admin.tickets.index'))
            ->assertInertia(fn (Assert $page) => $page->where('mentors.0.id', $mentor->id)->where('mentors.0.load', 0));
    }

    public function test_reviewing_a_violation_can_lift_the_automatic_suspension(): void
    {
        $member = $this->freelancer();
        $member->forceFill(['status' => UserStatus::Suspended, 'suspension_reason' => 'phone'])->save();
        $violation = Violation::factory()->create(['user_id' => $member->id]);

        $this->get(route('admin.moderation.violations'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Moderation/Violations')
                ->where('filters.status', 'unreviewed')
                ->has('violations.data', 1)
                ->where('counts.unreviewed', 1)
                ->where('violations.data.0.user.status', 'suspended'));

        $this->put(route('admin.violations.update', $violation), ['lift_suspension' => true])->assertSessionHasNoErrors();

        $this->assertNotNull($violation->fresh()->reviewed_at);
        $this->assertFalse($member->fresh()->isSuspended());

        $this->get(route('admin.moderation.violations'))->assertInertia(fn (Assert $page) => $page->has('violations.data', 0));
        $this->get(route('admin.moderation.violations', ['status' => 'reviewed']))->assertInertia(fn (Assert $page) => $page->has('violations.data', 1)->where('counts.reviewed', 1));
    }

    public function test_each_review_queue_has_its_own_page(): void
    {
        $this->get(route('admin.moderation.index'))->assertRedirect(route('admin.moderation.portfolio'));

        $this->get(route('admin.moderation.fields'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Moderation/Fields')->where('filters.status', 'pending_exam')->has('fields.data'));
        $this->get(route('admin.moderation.portfolio'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Moderation/Portfolio')->where('filters.status', 'pending_review')->has('media.data'));
        $this->get(route('admin.moderation.portfolio', ['status' => 'bogus']))->assertSessionHasErrors('status');

        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('panel.navigation', fn ($items) => collect($items)->where('group', 'تخلفات و بازبینی')->pluck('key')->all() === [
                'admin.moderation.fields', 'admin.moderation.violations', 'admin.moderation.portfolio',
            ]));
    }

    public function test_portfolio_files_are_approved_or_rejected_with_a_reason(): void
    {
        Storage::fake('local');
        $item = PortfolioItem::factory()->create(['freelancer_id' => $this->freelancer()->id, 'category_id' => $this->category('web-development')->id]);
        $media = PortfolioMedia::factory()->create(['portfolio_item_id' => $item->id]);

        $this->put(route('admin.portfolio-media.update', $media), ['status' => 'rejected'])->assertSessionHasErrors('rejection_reason');
        $this->put(route('admin.portfolio-media.update', $media), ['status' => 'rejected', 'rejection_reason' => 'اطلاعات تماس دارد'])->assertSessionHasNoErrors();
        $this->assertSame(ModerationStatus::Rejected, $media->fresh()->status);

        $this->put(route('admin.portfolio-media.update', $media), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(ModerationStatus::Approved, $media->fresh()->status);
        $this->assertNull($media->fresh()->rejection_reason);
        $this->assertSame($this->admin->id, $media->fresh()->reviewed_by);
    }

    public function test_field_exam_results_activate_or_reject_the_field(): void
    {
        $field = FreelancerField::factory()->pendingExam()->create([
            'freelancer_id' => $this->freelancer(activeField: null)->id,
            'category_id' => $this->category('design-creative')->id,
        ]);

        $this->put(route('admin.freelancer-fields.update', $field), ['status' => 'pending_exam'])->assertSessionHasErrors('status');
        $this->put(route('admin.freelancer-fields.update', $field), ['status' => 'active'])->assertSessionHasNoErrors();

        $this->assertSame(FreelancerFieldStatus::Active, $field->fresh()->status);
        $this->assertNotNull($field->fresh()->verified_at);
    }

    public function test_staff_publish_learning_content_for_members(): void
    {
        $this->post(route('admin.contents.store'), [
            'title' => 'آشنایی با امانت',
            'content_type' => 'video',
            'audience' => 'all',
            'purpose' => 'technical',
            'media_url' => 'https://www.aparat.com/v/abc',
            'is_published' => true,
        ])->assertSessionHasNoErrors();

        $content = LearningContent::firstWhere('title', 'آشنایی با امانت');
        $this->assertTrue($content->is_published);
        $this->assertNotNull($content->published_at);

        $this->post(route('admin.contents.store'), ['title' => 'بدون متن', 'content_type' => 'article', 'audience' => 'all', 'purpose' => 'technical'])
            ->assertSessionHasErrors('body');

        $this->get(route('admin.contents.index', ['published' => '1', 'type' => 'video']))
            ->assertInertia(fn (Assert $page) => $page->has('contents.data', 1)->where('contents.data.0.author.id', $this->admin->id));

        $this->delete(route('admin.contents.destroy', $content))->assertSessionHasNoErrors();
        $this->assertModelMissing($content);
    }

    public function test_the_transaction_ledger_is_read_only_and_filterable(): void
    {
        $employer = $this->employer(balance: 5_000_000);

        $this->get(route('admin.transactions.index', ['type' => 'deposit', 'search' => $employer->username]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.amount', 5_000_000)
                ->where('transactions.data.0.owner.id', $employer->id)
                ->where('summary.deposits', 5_000_000));

        $this->post('/panel/admin/transactions')->assertMethodNotAllowed();
    }

    private function contract(): Contract
    {
        $employer = $this->employer();
        $freelancer = $this->freelancer();
        $project = $this->openProject($employer, ['status' => ProjectStatus::InProgress]);
        $proposal = Proposal::factory()->create(['project_id' => $project->id, 'freelancer_id' => $freelancer->id, 'status' => 'accepted']);

        return Contract::factory()->create(['proposal_id' => $proposal->id]);
    }

    public function test_project_ids_that_do_not_exist_render_the_404_page(): void
    {
        $this->put(route('admin.projects.review', 999999), ['decision' => 'approve'])
            ->assertNotFound();

        $this->assertSame(0, Project::count());
    }
}
