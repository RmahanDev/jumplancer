<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BudgetType;
use App\Enums\ProjectStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProjectResource;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every project on the platform, including the review queue.
 */
class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'category' => ['nullable', 'integer'],
        ]);

        $projects = Project::query()
            ->with(['employer', 'category', 'mentor'])
            ->withCount('proposals')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', '%'.PersianText::normalize($search).'%'))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $query, int $category) => $query->whereIn(
                'category_id',
                Category::where('id', $category)->orWhere('parent_id', $category)->select('id'),
            ))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ProjectStatus::PendingReview->value])
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Admin/Projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
                'category' => $filters['category'] ?? null,
            ],
            'statusCounts' => Project::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'categories' => CategoryResource::collection(Category::topLevel()->with('children')->orderBy('sort_order')->get()),
            'mentors' => User::role(RoleName::Mentor)->orderBy('name')->get(['id', 'name'])->map(fn (User $mentor): array => ['id' => $mentor->id, 'name' => $mentor->name]),
            'options' => [
                'statuses' => array_column(ProjectStatus::cases(), 'value'),
                'budgetTypes' => array_column(BudgetType::cases(), 'value'),
            ],
            'routes' => [
                'update' => route('admin.projects.update', ':id'),
                'destroy' => route('admin.projects.destroy', ':id'),
                'review' => route('admin.projects.review', ':id'),
            ],
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        $this->toast(__('Project ":title" was updated.', ['title' => $project->title]));

        return back();
    }

    /**
     * Soft delete, so contracts and ledger rows keep their project.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        $this->toast(__('Project ":title" was deleted.', ['title' => $project->title]));

        return back();
    }
}
