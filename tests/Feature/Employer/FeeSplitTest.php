<?php

namespace Tests\Feature\Employer;

use App\Enums\AdminPermission;
use App\Enums\ExperienceLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Contract;
use App\Models\Milestone;
use App\Models\PlatformSetting;
use App\Models\Proposal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * The platform fee, the mentorship fee and the mentor's share of it (all editable in the platform
 * settings), and mentoring staying invisible to employers.
 */
class FeeSplitTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_a_payment_pays_the_freelancer_the_platform_and_the_mentor(): void
    {
        $employer = $this->employer(balance: 10_000_000);
        $freelancer = $this->freelancer(ExperienceLevel::Intermediate);
        $mentor = $this->mentor();
        $contract = $this->hire($employer, $freelancer, 4_000_000, mentorship: true);
        $contract->forceFill(['mentor_id' => $mentor->id])->save();

        $this->assertSame(25.0, $contract->fee_percent, '20% platform fee + 5% for the mentorship');
        $this->assertSame(3.5, $contract->mentor_share_percent);

        $this->actingAs($employer)->post(route('employer.milestones.store', $contract), ['title' => 'پیاده‌سازی', 'amount' => 2_000_000])->assertSessionHasNoErrors();
        $milestone = Milestone::sole();
        $this->put(route('employer.milestones.update', $milestone), ['action' => 'fund'])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->post(route('freelancer.milestones.submit', $milestone))->assertSessionHasNoErrors();
        $this->actingAs($employer)->put(route('employer.milestones.update', $milestone), ['action' => 'release'])->assertSessionHasNoErrors();

        $this->assertSame(1_500_000, $freelancer->wallet()->first()->balance, '2,000,000 minus 25%');
        $this->assertSame(
            [[TransactionType::EscrowRelease, 2_000_000], [TransactionType::Fee, -500_000]],
            $freelancer->wallet()->first()->transactions()->orderBy('id')->get()->map(fn ($row) => [$row->type, (int) $row->amount])->all(),
        );

        $payout = $mentor->wallet()->first()->transactions()->sole();
        $this->assertSame(TransactionType::MentorPayout, $payout->type);
        $this->assertSame(70_000, (int) $payout->amount, '3.5% of 2,000,000; the other 430,000 of the fee stays with the platform');
        $this->assertSame(70_000, $mentor->wallet()->first()->balance);
        $this->assertLedgerMatchesWallets();
    }

    public function test_without_a_mentor_the_whole_fee_stays_with_the_platform(): void
    {
        $employer = $this->employer(balance: 10_000_000);
        $freelancer = $this->freelancer();
        $contract = $this->hire($employer, $freelancer, 2_000_000);

        $this->assertSame(20.0, $contract->fee_percent);
        $this->assertSame(0.0, $contract->mentor_share_percent);

        $this->actingAs($employer)->post(route('employer.milestones.store', $contract), ['title' => 'تحویل', 'amount' => 1_000_000]);
        $milestone = Milestone::sole();
        $this->put(route('employer.milestones.update', $milestone), ['action' => 'fund']);
        $this->actingAs($freelancer)->post(route('freelancer.milestones.submit', $milestone));
        $this->actingAs($employer)->put(route('employer.milestones.update', $milestone), ['action' => 'release'])->assertSessionHasNoErrors();

        $this->assertSame(800_000, $freelancer->wallet()->first()->balance);
        $this->assertSame(0, Transaction::where('type', TransactionType::MentorPayout)->count());
    }

    public function test_staff_edit_the_fees_and_new_contracts_use_them(): void
    {
        $staff = $this->adminWith([AdminPermission::ManageSettings]);
        $this->actingAs($staff);

        $this->put(route('admin.settings.update', $this->setting('platform_fee_percent')), ['setting_value' => '15.5'])->assertSessionHasNoErrors();
        $this->put(route('admin.settings.update', $this->setting('mentorship_fee_percent')), ['setting_value' => '4'])->assertSessionHasNoErrors();
        $this->put(route('admin.settings.update', $this->setting('mentor_share_percent')), ['setting_value' => '2.75'])->assertSessionHasNoErrors();

        $this->assertSame(['platform' => 15.5, 'mentorship' => 4.0, 'mentor_share' => 2.75], PlatformSetting::fees());

        // The mentor is paid out of the fees: never more than platform + mentorship.
        $this->put(route('admin.settings.update', $this->setting('mentor_share_percent')), ['setting_value' => '20'])->assertSessionHasErrors('setting_value');
        $this->put(route('admin.settings.update', $this->setting('mentor_share_percent')), ['setting_value' => '2.755'])->assertSessionHasErrors('setting_value');
        $this->put(route('admin.settings.update', $this->setting('platform_fee_percent')), ['setting_value' => '97'])->assertSessionHasErrors('setting_value');
        $this->put(route('admin.settings.update', $this->setting('platform_fee_percent')), ['setting_value' => '-1'])->assertSessionHasErrors('setting_value');

        $employer = $this->employer(balance: 10_000_000);
        $contract = $this->hire($employer, $this->freelancer(ExperienceLevel::Intermediate), 2_000_000, mentorship: true);

        $this->assertSame(19.5, $contract->fee_percent);
        $this->assertSame(2.75, $contract->mentor_share_percent);

        $this->actingAs($this->adminWith([AdminPermission::ManageProjects]))
            ->put(route('admin.settings.update', $this->setting('platform_fee_percent')), ['setting_value' => '10'])
            ->assertForbidden();
    }

    public function test_employers_never_see_the_mentoring_of_their_freelancers(): void
    {
        $employer = $this->employer(balance: 10_000_000);
        $freelancer = $this->freelancer(ExperienceLevel::Intermediate);
        $mentor = $this->mentor();
        $contract = $this->hire($employer, $freelancer, 3_000_000, mentorship: true);
        $contract->forceFill(['mentor_id' => $mentor->id])->save();
        Proposal::factory()->create(['project_id' => $this->openProject($employer)->id, 'freelancer_id' => $freelancer->id, 'mentorship_requested' => true]);

        $this->actingAs($employer)->get(route('employer.contracts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contracts.data.0.id', $contract->id)
                ->missing('contracts.data.0.mentor')
                ->missing('contracts.data.0.mentorship_included')
                ->missing('contracts.data.0.is_free_mentorship')
                ->missing('contracts.data.0.fee_percent'))
            ->assertDontSee($mentor->name);

        $this->get(route('employer.proposals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 2)
                ->missing('proposals.data.0.mentorship_requested')
                ->missing('proposals.data.0.mentor_feedback')
                ->missing('proposals.data.1.mentorship_requested'))
            ->assertDontSee($mentor->name);

        $this->get(route('wallet.show'))
            ->assertInertia(fn (Assert $page) => $page->where('types', fn ($types) => collect($types)->intersect(['mentor_payout', 'mentorship_fee'])->isEmpty()));

        // Freelancers still see it on their side.
        $this->actingAs($freelancer)->get(route('freelancer.contracts.index'))
            ->assertInertia(fn (Assert $page) => $page->where('contracts.data.0.mentorship_included', true));
    }

    private function setting(string $key): PlatformSetting
    {
        return PlatformSetting::where('setting_key', $key)->sole();
    }

    private function hire(User $employer, User $freelancer, int $amount, bool $mentorship = false): Contract
    {
        $proposal = Proposal::factory()->create([
            'project_id' => $this->openProject($employer)->id,
            'freelancer_id' => $freelancer->id,
            'proposed_price' => $amount,
            'mentorship_requested' => $mentorship,
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
