<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BudgetType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two-level category tree, its skills and budget ranges.
 */
class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::topLevel()
            ->with([
                'budgetRanges',
                'children' => fn ($query) => $query->orderBy('sort_order')->with(['skills' => fn ($skills) => $skills->orderBy('name'), 'budgetRanges'])->withCount('projects'),
            ])
            ->withCount('projects')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Catalog/Index', [
            'categories' => CategoryResource::collection($categories),
            'budgetTypes' => array_column(BudgetType::cases(), 'value'),
            'routes' => [
                'store' => route('admin.categories.store'),
                'update' => route('admin.categories.update', ':id'),
                'destroy' => route('admin.categories.destroy', ':id'),
                'budgetRange' => route('admin.categories.budget-range', ':id'),
                'skillStore' => route('admin.skills.store'),
                'skillUpdate' => route('admin.skills.update', ':id'),
                'skillDestroy' => route('admin.skills.destroy', ':id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = Category::create($this->validated($request));

        $this->toast(__('Category ":name" was created.', ['name' => $category->name]));

        return back();
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validated($request, $category);

        if ($validated['parent_id'] !== null && $category->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => __('A category with sub-categories cannot move under another category.')]);
        }

        $category->update($validated);

        $this->toast(__('Category ":name" was updated.', ['name' => $category->name]));

        return back();
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists() || $category->skills()->exists() || $category->projects()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['category' => __('Only an empty category can be deleted: move its sub-categories, skills and projects first.')]);
        }

        $category->budgetRanges()->delete();
        $category->delete();

        $this->toast(__('Category ":name" was deleted.', ['name' => $category->name]));

        return back();
    }

    /**
     * @return array{name: string, slug: string, parent_id: ?int, sort_order: int}
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        $request->merge([
            'name' => PersianText::normalize($request->string('name')->toString()),
            'slug' => mb_strtolower($request->string('slug')->trim()->toString()),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories')->ignore($category)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id'), ...($category ? [Rule::notIn([$category->id])] : [])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'parent_id' => $validated['parent_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }
}
