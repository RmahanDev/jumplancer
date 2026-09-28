<?php

namespace App\Http\Controllers\Freelancer;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Freelancer\SaveProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(ProposalStatus::class)]])['status'] ?? null;
        $freelancer = $request->user();

        $proposals = $freelancer->proposals()
            ->with(['project.employer', 'mentorReviewer', 'contract'])
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Freelancer/Proposals/Index', [
            'proposals' => ProposalResource::collection($proposals),
            'filters' => ['status' => $status],
            'statusCounts' => $freelancer->proposals()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'routes' => [
                'update' => route('freelancer.proposals.update', ':id'),
                'destroy' => route('freelancer.proposals.destroy', ':id'),
                'projects' => route('freelancer.projects.index'),
                'contracts' => route('freelancer.contracts.index'),
            ],
        ]);
    }

    public function store(SaveProposalRequest $request): RedirectResponse
    {
        $project = Project::findOrFail($request->integer('project_id'));

        $proposal = new Proposal([
            ...$request->safe()->only(['cover_letter', 'proposed_price', 'delivery_days']),
            'mentorship_requested' => $request->boolean('mentorship_requested'),
            'status' => ProposalStatus::Pending,
        ]);
        $proposal->project()->associate($project);
        $proposal->freelancer()->associate($request->user());
        $proposal->save();

        $this->toast(__('Your proposal for ":title" was sent. Good luck!', ['title' => $project->title]));

        return back();
    }

    /**
     * A proposal can be edited until the employer acts on it.
     */
    public function update(SaveProposalRequest $request, Proposal $proposal): RedirectResponse
    {
        Gate::authorize('update', $proposal);

        if ($proposal->status !== ProposalStatus::Pending) {
            throw ValidationException::withMessages(['cover_letter' => __('Only pending proposals can be edited.')]);
        }

        $proposal->update([...$request->safe()->only(['cover_letter', 'proposed_price', 'delivery_days']), 'mentorship_requested' => $request->boolean('mentorship_requested')]);

        $this->toast(__('Your proposal was updated.'));

        return back();
    }

    /**
     * Withdraw: the row stays so the employer's history is intact.
     */
    public function destroy(Proposal $proposal): RedirectResponse
    {
        Gate::authorize('delete', $proposal);

        if (! in_array($proposal->status, [ProposalStatus::Pending, ProposalStatus::Shortlisted], true)) {
            throw ValidationException::withMessages(['proposal' => __('This proposal can no longer be withdrawn.')]);
        }

        $proposal->update(['status' => ProposalStatus::Withdrawn]);

        $this->toast(__('Your proposal was withdrawn.'), 'info');

        return back();
    }
}
