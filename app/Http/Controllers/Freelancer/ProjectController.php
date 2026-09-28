<?php

namespace App\Http\Controllers\Freelancer;

use App\Enums\BudgetType;
use App\Enums\FreelancerFieldStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProjectResource;
use App\Models\Category;
use App\Models\Project;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse open projects and send proposals (from a modal).
 */
class ProjectController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'budget_type' => ['nullable', Rule::enum(BudgetType::class)],
            'beginner' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'budget'])],
        ]);

        $freelancer = $request->user();

        $projects = Project::open()
            ->with(['category', 'employer', 'skills'])
            ->withCount('proposals')
            ->withExists(['proposals as has_proposed' => fn (Builder $query) => $query->where('freelancer_id', $freelancer->id)])
            ->where('employer_id', '!=', $freelancer->id)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.PersianText::normalize($search).'%';
                $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($filters['category'] ?? null, fn (Builder $query, int $category) => $query->whereIn(
                'category_id',
                Category::where('id', $category)->orWhere('parent_id', $category)->select('id'),
            ))
            ->when($filters['budget_type'] ?? null, fn (Builder $query, string $type) => $query->where('budget_type', $type))
            ->when($filters['beginner'] ?? false, fn (Builder $query) => $query->where('is_beginner_friendly', true))
            ->when(($filters['sort'] ?? 'newest') === 'budget', fn (Builder $query) => $query->orderByDesc('budget_max'))
            ->latest('published_at')
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Freelancer/Projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'category' => $filters['category'] ?? null,
                'budget_type' => $filters['budget_type'] ?? null,
                'beginner' => (bool) ($filters['beginner'] ?? false),
                'sort' => $filters['sort'] ?? 'newest',
            ],
            'categories' => CategoryResource::collection(Category::topLevel()->with('children')->orderBy('sort_order')->get()),
            'activeFields' => $freelancer->freelancerFields()
                ->where('status', FreelancerFieldStatus::Active)
                ->pluck('category_id'),
            'routes' => [
                'propose' => route('freelancer.proposals.store'),
                'fields' => route('freelancer.fields.index'),
            ],
        ]);
    }
}
