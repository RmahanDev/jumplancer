<?php

namespace App\Http\Controllers\Employer;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProposalResource;
use App\Models\PlatformSetting;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Proposals received on the employer's projects: shortlist, reject or hire.
 */
class ProposalController extends Controller
{
    public function index(Request $request): Response
    {
        $employer = $request->user();

        $filters = $request->validate([
            'project' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ProposalStatus::class)],
        ]);

        $proposals = Proposal::query()
            ->with(['project', 'freelancer.freelancerProfile', 'mentorReviewer', 'contract'])
            ->whereHas('project', fn (Builder $query) => $query->where('employer_id', $employer->id))
            ->where('status', '!=', ProposalStatus::Draft)
            ->when($filters['project'] ?? null, fn (Builder $query, int $project) => $query->where('project_id', $project))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [ProposalStatus::Shortlisted->value, ProposalStatus::Pending->value])
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Employer/Proposals/Index', [
            'proposals' => ProposalResource::collection($proposals),
            'filters' => ['project' => $filters['project'] ?? null, 'status' => $filters['status'] ?? null],
            'projects' => $employer->postedProjects()->latest()->get(['id', 'title'])->map(fn ($project): array => [
                'id' => $project->id,
                'title' => $project->title,
            ]),
            'hiring' => [
                'depositPercent' => PlatformSetting::hireDepositPercent(),
                'balance' => $employer->ensureWallet()->balance,
            ],
            'routes' => [
                'wallet' => route('wallet.show'),
                'update' => route('employer.proposals.update', ':id'),
                'hire' => route('employer.contracts.store', ':id'),
                'contracts' => route('employer.contracts.index'),
            ],
        ]);
    }

    /**
     * Shortlist, reject, or move a proposal back to pending.
     */
    public function update(Request $request, Proposal $proposal): RedirectResponse
    {
        Gate::authorize('respond', $proposal);

        $validated = $request->validate([
            'status' => ['required', Rule::in([ProposalStatus::Pending->value, ProposalStatus::Shortlisted->value, ProposalStatus::Rejected->value])],
        ]);

        if (! in_array($proposal->status, [ProposalStatus::Pending, ProposalStatus::Shortlisted], true)) {
            throw ValidationException::withMessages(['status' => __('This proposal was already decided.')]);
        }

        $proposal->update(['status' => $validated['status']]);

        $this->toast(match (ProposalStatus::from($validated['status'])) {
            ProposalStatus::Shortlisted => __('Added to the shortlist.'),
            ProposalStatus::Rejected => __('The proposal was rejected.'),
            default => __('The proposal is pending again.'),
        }, $validated['status'] === ProposalStatus::Rejected->value ? 'info' : 'success');

        return back();
    }
}
