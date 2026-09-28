<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentAudience;
use App\Enums\ContentPurpose;
use App\Enums\LearningContentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveLearningContentRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\LearningContentResource;
use App\Models\Category;
use App\Models\LearningContent;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * All learning content, whoever wrote it ("content.manage").
 */
class LearningContentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(LearningContentType::class)],
            'published' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $contents = LearningContent::query()
            ->with(['author', 'category'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', '%'.PersianText::normalize($search).'%'))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('content_type', $type))
            ->when(isset($filters['published']), fn (Builder $query) => $query->where('is_published', $filters['published'] === '1'))
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Admin/Contents/Index', [
            'contents' => LearningContentResource::collection($contents),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'type' => $filters['type'] ?? null,
                'published' => $filters['published'] ?? null,
            ],
            ...self::formOptions(),
            'routes' => [
                'store' => route('admin.contents.store'),
                'update' => route('admin.contents.update', ':id'),
                'destroy' => route('admin.contents.destroy', ':id'),
            ],
        ]);
    }

    public function store(SaveLearningContentRequest $request): RedirectResponse
    {
        $content = $request->user()->learningContents()->create($request->contentAttributes());

        $this->toast(__('":title" was saved.', ['title' => $content->title]));

        return back();
    }

    public function update(SaveLearningContentRequest $request, LearningContent $learningContent): RedirectResponse
    {
        $learningContent->update($request->contentAttributes($learningContent->published_at?->toDateTimeString()));

        $this->toast(__('":title" was saved.', ['title' => $learningContent->title]));

        return back();
    }

    public function destroy(LearningContent $learningContent): RedirectResponse
    {
        $learningContent->delete();

        $this->toast(__('":title" was deleted.', ['title' => $learningContent->title]));

        return back();
    }

    /**
     * Select options shared with the mentor's content page.
     *
     * @return array{categories: mixed, options: array<string, list<string>>}
     */
    public static function formOptions(): array
    {
        return [
            'categories' => CategoryResource::collection(Category::topLevel()->with('children')->orderBy('sort_order')->get()),
            'options' => [
                'types' => array_column(LearningContentType::cases(), 'value'),
                'audiences' => array_column(ContentAudience::cases(), 'value'),
                'purposes' => array_column(ContentPurpose::cases(), 'value'),
            ],
        ];
    }
}
