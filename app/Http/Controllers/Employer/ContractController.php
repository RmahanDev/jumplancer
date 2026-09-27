<?php

namespace App\Http\Controllers\Employer;

use App\Enums\ContractStatus;
use App\Enums\ExperienceLevel;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\PlatformSetting;
use App\Models\Proposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hiring (proposal to contract) and running contracts with escrowed milestones.
 */
class ContractController extends Controller
{
    /**
     * Base platform fee, and the fee when mentoring support is included (v2 rules).
     */
    private const FEE_PERCENT = 20;

    private const FEE_WITH_MENTORSHIP_PERCENT = 25;

    public function index(Request $request): Response
    {
        $employer = $request->user();
        $status = $request->validate(['status' => ['nullable', Rule::enum(ContractStatus::class)]])['status'] ?? null;

        $contracts = $employer->employerContracts()
            ->with(['project', 'freelancer', 'mentor', 'milestones' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
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
                'wallet' => route('wallet.show'),
            ],
        ]);
    }

    /**
     * Hire: turn a proposal into a contract and close the project to other proposals.
     */
    public function store(Request $request, Proposal $proposal): RedirectResponse
    {
        Gate::authorize('respond', $proposal);

        $validated = $request->validate([
            'mentorship_included' => ['boolean'],
        ]);

        $contract = DB::transaction(function () use ($proposal, $validated): Contract {
            $proposal = Proposal::with(['project', 'freelancer.freelancerProfile'])->lockForUpdate()->findOrFail($proposal->id);
            $project = $proposal->project;

            if (! in_array($proposal->status, [ProposalStatus::Pending, ProposalStatus::Shortlisted], true) || $project->status !== ProjectStatus::Open) {
                throw ValidationException::withMessages(['proposal' => __('This proposal can no longer be accepted.')]);
            }

            $withMentorship = (bool) ($validated['mentorship_included'] ?? false);
            $isFreeMentorship = $withMentorship && $this->useFreeMentorship($proposal);

            $contract = Contract::create([
                'project_id' => $project->id,
                'proposal_id' => $proposal->id,
                'employer_id' => $project->employer_id,
                'freelancer_id' => $proposal->freelancer_id,
                'mentor_id' => $withMentorship ? $project->mentor_id : null,
                'amount' => $proposal->proposed_price,
                'mentorship_included' => $withMentorship,
                'is_free_mentorship' => $isFreeMentorship,
                'fee_percent' => $withMentorship && ! $isFreeMentorship ? self::FEE_WITH_MENTORSHIP_PERCENT : self::FEE_PERCENT,
                'status' => ContractStatus::Active,
                'started_at' => now(),
            ]);

            $proposal->update(['status' => ProposalStatus::Accepted]);
            $project->proposals()
                ->whereKeyNot($proposal->id)
                ->whereIn('status', [ProposalStatus::Pending, ProposalStatus::Shortlisted])
                ->update(['status' => ProposalStatus::Rejected]);
            $project->update(['status' => ProjectStatus::InProgress]);

            return $contract;
        });

        $this->toast(__('You hired :name. Add the first milestone and fund it to get started.', [
            'name' => $contract->freelancer->name,
        ]));

        return to_route('employer.contracts.index');
    }

    /**
     * Complete or cancel a contract. Money still in escrow must be released first.
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

        if ($inEscrow) {
            throw ValidationException::withMessages(['status' => __('Release or settle the funded milestones before closing the contract.')]);
        }

        $status = ContractStatus::from($validated['status']);

        DB::transaction(function () use ($contract, $status): void {
            $contract->update([
                'status' => $status,
                'completed_at' => $status === ContractStatus::Completed ? now() : null,
            ]);

            $contract->project->update([
                'status' => $status === ContractStatus::Completed ? ProjectStatus::Completed : ProjectStatus::Cancelled,
            ]);
        });

        $this->toast($status === ContractStatus::Completed
            ? __('The contract is complete. Leave a review for the freelancer!')
            : __('The contract was cancelled.'), $status === ContractStatus::Completed ? 'success' : 'info');

        return back();
    }

    /**
     * v4 rule 1: the first mentorships of a beginner freelancer are free (no extra 5% fee).
     */
    private function useFreeMentorship(Proposal $proposal): bool
    {
        $profile = $proposal->freelancer->freelancerProfile;
        $allowance = PlatformSetting::firstWhere('setting_key', 'beginner_free_mentorships')?->typed_value ?? 2;

        if ($profile === null || $profile->level !== ExperienceLevel::Beginner || $profile->free_mentorships_used >= $allowance) {
            return false;
        }

        $profile->increment('free_mentorships_used');

        return true;
    }
}
