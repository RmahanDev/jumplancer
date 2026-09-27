<?php

namespace App\Http\Controllers\Employer;

use App\Enums\ContractStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProposalResource;
use App\Models\Milestone;
use App\Models\Proposal;
use App\Services\ProjectPostingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProjectPostingService $posting): Response
    {
        $employer = $request->user();
        $wallet = $employer->ensureWallet();
        $myProposals = Proposal::whereHas('project', fn (Builder $query) => $query->where('employer_id', $employer->id));
        $myMilestones = Milestone::whereHas('contract', fn (Builder $query) => $query->where('employer_id', $employer->id));

        return Inertia::render('Employer/Dashboard', [
            'stats' => [
                'open_projects' => $employer->postedProjects()->where('status', ProjectStatus::Open)->count(),
                'pending_review' => $employer->postedProjects()->where('status', ProjectStatus::PendingReview)->count(),
                'new_proposals' => (clone $myProposals)->where('status', ProposalStatus::Pending)->count(),
                'active_contracts' => $employer->employerContracts()->where('status', ContractStatus::Active)->count(),
                'awaiting_release' => (clone $myMilestones)->where('status', MilestoneStatus::Submitted)->count(),
                'paid' => (int) (clone $myMilestones)->where('status', MilestoneStatus::Released)->sum('amount'),
                'balance' => $wallet->balance,
                'escrow' => $wallet->held_balance,
            ],
            'projectsByStatus' => $employer->postedProjects()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'posting' => ['next' => $posting->nextPostingFor($employer->id)['type']?->value],
            'recentProposals' => ProposalResource::collection(
                (clone $myProposals)->with(['project', 'freelancer.freelancerProfile'])
                    ->whereIn('status', [ProposalStatus::Pending, ProposalStatus::Shortlisted])
                    ->latest()
                    ->latest('id')
                    ->limit(5)
                    ->get(),
            ),
            'needsAttention' => ProjectResource::collection(
                $employer->postedProjects()
                    ->with('category')
                    ->where('status', ProjectStatus::Draft)
                    ->latest('updated_at')
                    ->limit(4)
                    ->get(),
            ),
            'routes' => [
                'projects' => route('employer.projects.index'),
                'proposals' => route('employer.proposals.index'),
                'contracts' => route('employer.contracts.index'),
                'plans' => route('employer.plans.index'),
                'wallet' => route('wallet.show'),
            ],
        ]);
    }
}
