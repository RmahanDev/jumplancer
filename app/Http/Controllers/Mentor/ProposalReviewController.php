<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Proposals a mentor can coach: on projects they supervise, or sent by their active mentees.
 */
class ProposalReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['pending', 'reviewed'])]])['tab'] ?? 'pending';
        $mentor = $request->user();

        $proposals = $this->reviewable($mentor)
            ->with(['project.employer', 'freelancer.freelancerProfile', 'mentorReviewer'])
            ->when($tab === 'pending', fn (Builder $query) => $query->whereNull('mentor_reviewed_by'))
            ->when($tab === 'reviewed', fn (Builder $query) => $query->whereNotNull('mentor_reviewed_by'))
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Mentor/Reviews/Index', [
            'tab' => $tab,
            'proposals' => ProposalResource::collection($proposals),
            'counts' => [
                'pending' => $this->reviewable($mentor)->whereNull('mentor_reviewed_by')->count(),
                'reviewed' => $this->reviewable($mentor)->whereNotNull('mentor_reviewed_by')->count(),
            ],
            'routes' => [
                'update' => route('mentor.reviews.update', ':id'),
            ],
        ]);
    }

    public function update(Request $request, Proposal $proposal): RedirectResponse
    {
        Gate::authorize('review', $proposal);

        $validated = $request->validate([
            'mentor_feedback' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $proposal->update([
            'mentor_feedback' => $validated['mentor_feedback'],
            'mentor_reviewed_by' => $request->user()->id,
        ]);

        $this->toast(__('Your feedback was sent to :name.', ['name' => $proposal->freelancer->name]));

        return back();
    }

    /**
     * @return Builder<Proposal>
     */
    private function reviewable(User $mentor): Builder
    {
        return Proposal::query()->reviewableBy($mentor);
    }
}
