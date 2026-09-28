<?php

namespace App\Http\Controllers\Freelancer;

use App\Enums\ContractStatus;
use App\Enums\FreelancerFieldStatus;
use App\Enums\ProposalStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProposalResource;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $freelancer = $request->user()->load('freelancerProfile');
        $wallet = $freelancer->ensureWallet();
        $activeFieldIds = $freelancer->freelancerFields()->where('status', FreelancerFieldStatus::Active)->pluck('category_id');

        $earnings = $wallet->transactions()
            ->where('status', TransactionStatus::Succeeded)
            ->whereIn('type', [TransactionType::EscrowRelease, TransactionType::Fee])
            ->where('created_at', '>=', now()->subDays(89)->startOfDay())
            ->get(['amount', 'created_at']);

        return Inertia::render('Freelancer/Dashboard', [
            'stats' => [
                'active_proposals' => $freelancer->proposals()->whereIn('status', [ProposalStatus::Pending, ProposalStatus::Shortlisted])->count(),
                'active_contracts' => $freelancer->freelancerContracts()->where('status', ContractStatus::Active)->count(),
                'completed_contracts' => $freelancer->freelancerContracts()->where('status', ContractStatus::Completed)->count(),
                'earned' => (int) $wallet->transactions()
                    ->where('status', TransactionStatus::Succeeded)
                    ->whereIn('type', [TransactionType::EscrowRelease, TransactionType::Fee])
                    ->sum('amount'),
                'balance' => $wallet->balance,
                'level' => $freelancer->freelancerProfile?->level->value,
                'readiness' => $freelancer->freelancerProfile?->readiness_score ?? 0,
            ],
            'checklist' => $this->checklist($freelancer, $activeFieldIds->isNotEmpty()),
            'earnings' => $earnings->map(fn ($row): array => [
                'date' => $row->created_at->toDateString(),
                'amount' => $row->amount,
            ])->values(),
            'recommended' => ProjectResource::collection(
                Project::open()
                    ->with(['category', 'employer', 'skills'])
                    ->withCount('proposals')
                    ->whereIn('category_id', Category::whereIn('parent_id', $activeFieldIds)->orWhereIn('id', $activeFieldIds)->select('id'))
                    ->where('employer_id', '!=', $freelancer->id)
                    ->whereDoesntHave('proposals', fn (Builder $query) => $query->where('freelancer_id', $freelancer->id))
                    ->orderByDesc('is_beginner_friendly')
                    ->latest('published_at')
                    ->limit(4)
                    ->get(),
            ),
            'recentProposals' => ProposalResource::collection(
                $freelancer->proposals()->with('project.employer')->latest()->latest('id')->limit(5)->get(),
            ),
            'routes' => [
                'projects' => route('freelancer.projects.index'),
                'proposals' => route('freelancer.proposals.index'),
                'fields' => route('freelancer.fields.index'),
                'portfolio' => route('freelancer.portfolio.index'),
                'profile' => route('profile.edit'),
                'tickets' => route('tickets.index'),
            ],
        ]);
    }

    /**
     * Steps that make a beginner's profile convincing to employers.
     *
     * @return list<array{key: string, done: bool}>
     */
    private function checklist(User $freelancer, bool $hasActiveField): array
    {
        return [
            ['key' => 'headline', 'done' => filled($freelancer->freelancerProfile?->headline)],
            ['key' => 'bio', 'done' => filled($freelancer->bio)],
            ['key' => 'phone', 'done' => filled($freelancer->phone)],
            ['key' => 'field', 'done' => $hasActiveField],
            ['key' => 'portfolio', 'done' => $freelancer->portfolioItems()->exists()],
            ['key' => 'proposal', 'done' => $freelancer->proposals()->exists()],
        ];
    }
}
