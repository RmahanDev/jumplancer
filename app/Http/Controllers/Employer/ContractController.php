<?php

namespace App\Http\Controllers\Employer;

use App\Enums\ContractStatus;
use App\Enums\ExperienceLevel;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\PlatformSetting;
use App\Models\Proposal;
use App\Services\WalletLedger;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hiring (proposal to contract) and running contracts with escrowed milestones.
 *
 * Hiring holds a good-faith deposit (platform setting "hire_deposit_percent", 45% by default)
 * from the employer's wallet. Milestones spend it first; an employer cannot walk away while
 * money is held, a dispute is opened instead and an expert decides where the money goes.
 */
class ContractController extends Controller
{
    /**
     * Base platform fee, and the fee when the freelancer asked for a mentor (v2 rules).
     */
    public const FEE_PERCENT = 20;

    public const FEE_WITH_MENTORSHIP_PERCENT = 25;

    public function __construct(private readonly WalletLedger $ledger) {}

    public function index(Request $request): Response
    {
        $employer = $request->user();
        $status = $request->validate(['status' => ['nullable', Rule::enum(ContractStatus::class)]])['status'] ?? null;

        $contracts = $employer->employerContracts()
            ->with(['project', 'freelancer', 'mentor', 'openDispute', 'milestones' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->withExists(['reviews as reviewed_by_viewer' => fn ($query) => $query->where('reviewer_id', $employer->id)])
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ContractStatus::Active->value])
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Employer/Contracts/Index', [
            'contracts' => ContractResource::collection($contracts),
            'filters' => ['status' => $status],
            'balance' => $employer->ensureWallet()->balance,
            'routes' => [
                'update' => route('employer.contracts.update', ':id'),
                'milestoneStore' => route('employer.milestones.store', ':id'),
                'milestoneUpdate' => route('employer.milestones.update', ':id'),
                'review' => route('employer.reviews.store', ':id'),
                'dispute' => route('contracts.disputes.store', ':id'),
                'wallet' => route('wallet.show'),
            ],
        ]);
    }

    /**
     * Hire: hold the good-faith deposit, turn the proposal into a contract and close the project
     * to other proposals. When the freelancer asked for a mentor, a ticket joins the mentors' queue.
     */
    public function store(Request $request, Proposal $proposal): RedirectResponse
    {
        Gate::authorize('respond', $proposal);

        $request->validate([
            'accept_deposit_terms' => ['accepted'],
        ], [
            'accept_deposit_terms.accepted' => __('Confirm the good-faith deposit terms to hire.'),
        ]);

        $contract = DB::transaction(function () use ($proposal): Contract {
            $proposal = Proposal::with(['project', 'freelancer.freelancerProfile'])->lockForUpdate()->findOrFail($proposal->id);
            $project = $proposal->project;

            if (! in_array($proposal->status, [ProposalStatus::Pending, ProposalStatus::Shortlisted], true) || $project->status !== ProjectStatus::Open) {
                throw ValidationException::withMessages(['proposal' => __('This proposal can no longer be accepted.')]);
            }

            // Mentoring is the freelancer's choice, made on the proposal.
            $withMentorship = $proposal->mentorship_requested;
            $isFreeMentorship = $withMentorship && $this->useFreeMentorship($proposal);

            $contract = Contract::create([
                'project_id' => $project->id,
                'proposal_id' => $proposal->id,
                'employer_id' => $project->employer_id,
                'freelancer_id' => $proposal->freelancer_id,
                'amount' => $proposal->proposed_price,
                'mentorship_included' => $withMentorship,
                'is_free_mentorship' => $isFreeMentorship,
                'fee_percent' => $withMentorship && ! $isFreeMentorship ? self::FEE_WITH_MENTORSHIP_PERCENT : self::FEE_PERCENT,
                'status' => ContractStatus::Active,
                'started_at' => now(),
            ]);

            $contract->setRelation('project', $project);
            $this->ledger->holdHireDeposit($contract, PlatformSetting::hireDepositPercent());

            if ($withMentorship) {
                $this->queueMentorshipTicket($contract, $proposal);
            }

            $proposal->update(['status' => ProposalStatus::Accepted]);
            $project->proposals()
                ->whereKeyNot($proposal->id)
                ->whereIn('status', [ProposalStatus::Pending, ProposalStatus::Shortlisted])
                ->update(['status' => ProposalStatus::Rejected]);
            $project->update(['status' => ProjectStatus::InProgress]);

            return $contract;
        });

        $this->toast($contract->deposit_amount > 0
            ? __('You hired :name. :deposit Toman is held as the good-faith deposit; the first milestones are paid from it.', [
                'name' => $contract->freelancer->name,
                'deposit' => PersianText::number($contract->deposit_amount),
            ])
            : __('You hired :name. Add the first milestone and fund it to get started.', [
                'name' => $contract->freelancer->name,
            ]));

        return to_route('employer.contracts.index');
    }

    /**
     * Complete or cancel a contract. Funded milestones must be settled first; the unused deposit
     * returns on completion. Cancelling while money is held needs an expert: open a dispute.
     */
    public function update(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('manage', $contract);

        $validated = $request->validate([
            'status' => ['required', Rule::in([ContractStatus::Completed->value, ContractStatus::Cancelled->value])],
        ]);

        if ($contract->status !== ContractStatus::Active) {
            throw ValidationException::withMessages(['status' => __('Only active contracts can be closed.')]);
        }

        $inEscrow = $contract->milestones()->whereIn('status', [MilestoneStatus::Funded, MilestoneStatus::Submitted, MilestoneStatus::Approved])->exists();

        $status = ContractStatus::from($validated['status']);

        if ($inEscrow && $status === ContractStatus::Completed) {
            throw ValidationException::withMessages(['status' => __('Release or settle the funded milestones before closing the contract.')]);
        }

        if ($status === ContractStatus::Cancelled && ($inEscrow || $contract->deposit_balance > 0)) {
            throw ValidationException::withMessages(['status' => __('The good-faith deposit is held until an expert decides. Open a dispute and explain why you want to cancel.')]);
        }

        $refunded = DB::transaction(function () use ($contract, $status): int {
            $refunded = $status === ContractStatus::Completed ? $this->ledger->refundDeposit($contract) : 0;

            $contract->update([
                'status' => $status,
                'completed_at' => $status === ContractStatus::Completed ? now() : null,
            ]);

            $contract->project->update([
                'status' => $status === ContractStatus::Completed ? ProjectStatus::Completed : ProjectStatus::Cancelled,
            ]);

            return $refunded;
        });

        $message = match (true) {
            $status === ContractStatus::Cancelled => __('The contract was cancelled.'),
            $refunded > 0 => __('The contract is complete and :amount Toman of unused deposit is back in your wallet. Leave a review for the freelancer!', ['amount' => PersianText::number($refunded)]),
            default => __('The contract is complete. Leave a review for the freelancer!'),
        };

        $this->toast($message, $status === ContractStatus::Completed ? 'success' : 'info');

        return back();
    }

    /**
     * The freelancer asked for a mentor: open a ticket in the mentors' queue, linked to the contract.
     */
    private function queueMentorshipTicket(Contract $contract, Proposal $proposal): void
    {
        $proposal->freelancer->tickets()->create([
            'contract_id' => $contract->id,
            'ticket_type' => TicketType::Technical,
            'channel' => TicketChannel::Ticket,
            'subject' => Str::limit(__('Mentoring for project: :title', ['title' => $contract->project->title]), 200, ''),
            'message' => __('The freelancer asked for a mentor on this project. Contract amount: :amount Toman, delivery in :days days.', [
                'amount' => PersianText::number($contract->amount),
                'days' => PersianText::number($proposal->delivery_days),
            ]),
            'status' => TicketStatus::Open,
        ]);
    }

    /**
     * v4 rule 1: the first mentorships of a beginner freelancer are free (no extra 5% fee).
     */
    private function useFreeMentorship(Proposal $proposal): bool
    {
        $profile = $proposal->freelancer->freelancerProfile;
        $allowance = (int) PlatformSetting::valueOf('beginner_free_mentorships', 2);

        if ($profile === null || $profile->level !== ExperienceLevel::Beginner || $profile->free_mentorships_used >= $allowance) {
            return false;
        }

        $profile->increment('free_mentorships_used');

        return true;
    }
}
