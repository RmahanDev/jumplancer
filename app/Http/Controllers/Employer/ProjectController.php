<?php

namespace App\Http\Controllers\Employer;

use App\Enums\BudgetType;
use App\Enums\ContractStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\SaveProjectRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Category;
use App\Models\Project;
use App\Services\ProjectPostingService;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectPostingService $posting): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $employer = $request->user();

        $projects = $employer->postedProjects()
            ->with(['category', 'skills', 'mentor'])
            ->withCount('proposals')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('title', 'like', '%'.PersianText::normalize($search).'%'))
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        $next = $posting->nextPostingFor($employer->id);

        return Inertia::render('Employer/Projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'filters' => ['status' => $filters['status'] ?? null, 'search' => $filters['search'] ?? null],
            'statusCounts' => $employer->postedProjects()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'categories' => CategoryResource::collection(
                Category::topLevel()->with(['children' => fn ($query) => $query->orderBy('sort_order')->with(['skills', 'budgetRanges']), 'budgetRanges'])->orderBy('sort_order')->get(),
            ),
            'budgetTypes' => array_column(BudgetType::cases(), 'value'),
            'posting' => [
                'next' => $next['type']?->value,
                'second_free_until' => $next['second_free_until'],
                'subscription' => $next['subscription'] ? new SubscriptionResource($next['subscription']) : null,
            ],
            'routes' => [
                'store' => route('employer.projects.store'),
                'update' => route('employer.projects.update', ':id'),
                'destroy' => route('employer.projects.destroy', ':id'),
                'publish' => route('employer.projects.publish', ':id'),
                'proposals' => route('employer.proposals.index'),
                'plans' => route('employer.plans.index'),
            ],
        ]);
    }

    public function store(SaveProjectRequest $request, ProjectPostingService $posting): RedirectResponse
    {
        $this->ensureCanPost($request, $posting);

        $project = DB::transaction(function () use ($request): Project {
            $project = $request->user()->postedProjects()->create([
                ...$this->attributes($request),
                'status' => ProjectStatus::Draft,
            ]);
            $project->skills()->sync($request->validated('skills', []));

            return $project;
        });

        if ($request->boolean('submit')) {
            $posting->submitForReview($project);
            $this->toast(__('Project ":title" was sent for review. It goes live once approved.', ['title' => $project->title]));
        } else {
            $this->toast(__('Project ":title" was saved as a draft.', ['title' => $project->title]));
        }

        return back();
    }

    /**
     * Drafts and live projects can be edited; a project under review or in progress is locked.
     */
    public function update(SaveProjectRequest $request, Project $project, ProjectPostingService $posting): RedirectResponse
    {
        if (! in_array($project->status, [ProjectStatus::Draft, ProjectStatus::Open], true)) {
            throw ValidationException::withMessages(['title' => __('This project cannot be edited in its current state.')]);
        }

        DB::transaction(function () use ($request, $project): void {
            $project->update($this->attributes($request));
            $project->skills()->sync($request->validated('skills', []));
        });

        if ($request->boolean('submit') && $project->status === ProjectStatus::Draft) {
            $posting->submitForReview($project);
            $this->toast(__('Project ":title" was sent for review. It goes live once approved.', ['title' => $project->title]));
        } else {
            $this->toast(__('Project ":title" was updated.', ['title' => $project->title]));
        }

        return back();
    }

    /**
     * Unpublished projects are deleted; a live project without a contract is cancelled instead.
     */
    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        if (in_array($project->status, [ProjectStatus::Draft, ProjectStatus::PendingReview], true)) {
            $project->delete();
            $this->toast(__('Project ":title" was deleted.', ['title' => $project->title]));

            return back();
        }

        $hasContract = $project->contracts()->whereIn('status', [ContractStatus::Active, ContractStatus::Disputed])->exists();

        if ($project->status !== ProjectStatus::Open || $hasContract) {
            throw ValidationException::withMessages(['project' => __('A project with an active contract cannot be cancelled.')]);
        }

        $project->update(['status' => ProjectStatus::Cancelled]);
        $this->toast(__('Project ":title" was cancelled.', ['title' => $project->title]), 'info');

        return back();
    }

    /**
     * Fail before saving anything when "save and send for review" has no posting left to use.
     *
     * @throws ValidationException
     */
    private function ensureCanPost(SaveProjectRequest $request, ProjectPostingService $posting): void
    {
        if ($request->boolean('submit') && $posting->nextPostingFor($request->user()->id)['type'] === null) {
            throw ValidationException::withMessages([
                'project' => __('Your free postings are used up. Buy a plan to publish more projects.'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SaveProjectRequest $request): array
    {
        return [
            ...$request->safe()->only(['title', 'description', 'category_id', 'budget_type', 'budget_min', 'budget_max', 'deadline']),
            'is_beginner_friendly' => $request->boolean('is_beginner_friendly', true),
        ];
    }
}
