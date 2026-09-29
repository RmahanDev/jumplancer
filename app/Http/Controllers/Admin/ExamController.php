<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssessmentScope;
use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveExamRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ExamResource;
use App\Models\Assessment;
use App\Models\Category;
use App\Models\Skill;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The exam builder ("exams.manage"): skill exams that freelancers pass to show a verified skill.
 */
class ExamController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $exams = Assessment::query()
            ->with(['category', 'skill', 'author'])
            ->withCount([
                'questions',
                'attempts' => fn (Builder $query) => $query->whereNot('status', AttemptStatus::InProgress),
                'attempts as passed_count' => fn (Builder $query) => $query->where('status', AttemptStatus::Passed),
                'attempts as in_progress_count' => fn (Builder $query) => $query->where('status', AttemptStatus::InProgress),
            ])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', '%'.PersianText::normalize($search).'%'))
            ->when($filters['category'] ?? null, fn (Builder $query, int $category) => $query->whereIn(
                'category_id',
                Category::where('id', $category)->orWhere('parent_id', $category)->select('id'),
            ))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Admin/Exams/Index', [
            'exams' => ExamResource::collection($exams),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'category' => $filters['category'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'categories' => CategoryResource::collection(Category::topLevel()->with('children')->orderBy('sort_order')->get()),
            'routes' => [
                'create' => route('admin.exams.create'),
                'edit' => route('admin.exams.edit', ':id'),
                'status' => route('admin.exams.status', ':id'),
                'destroy' => route('admin.exams.destroy', ':id'),
            ],
        ]);
    }

    public function create(): Response
    {
        return $this->builder(null);
    }

    public function edit(Assessment $assessment): Response
    {
        return $this->builder($assessment->load(['questions.options', 'category', 'skill']));
    }

    public function store(SaveExamRequest $request): RedirectResponse
    {
        $exam = DB::transaction(function () use ($request): Assessment {
            $exam = new Assessment(['scope' => AssessmentScope::Skill]);
            $exam->forceFill(['created_by' => $request->user()->id]);

            return $this->save($exam, $request);
        });

        $this->toast(__('Exam ":title" was saved with :count questions.', [
            'title' => $exam->title,
            'count' => PersianText::number($exam->questions()->count()),
        ]));

        return to_route('admin.exams.index');
    }

    public function update(SaveExamRequest $request, Assessment $assessment): RedirectResponse
    {
        if ($assessment->attempts()->where('status', AttemptStatus::InProgress)->exists()) {
            throw ValidationException::withMessages(['exam' => __('A freelancer is taking this exam right now. Try again when they finish.')]);
        }

        DB::transaction(fn () => $this->save($assessment, $request));

        $this->toast(__('Exam ":title" was updated.', ['title' => $assessment->title]));

        return to_route('admin.exams.index');
    }

    /**
     * Open or close an exam for freelancers.
     */
    public function status(Request $request, Assessment $assessment): RedirectResponse
    {
        $active = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];
        $assessment->update(['is_active' => $active]);

        $this->toast($active ? __('The exam is open to freelancers.') : __('The exam is hidden from freelancers.'), $active ? 'success' : 'info');

        return back();
    }

    /**
     * Exams with attempts keep their history: they can only be hidden.
     */
    public function destroy(Assessment $assessment): RedirectResponse
    {
        if ($assessment->attempts()->exists()) {
            throw ValidationException::withMessages(['exam' => __('Freelancers already took this exam; hide it instead of deleting it.')]);
        }

        $assessment->delete();

        $this->toast(__('Exam ":title" was deleted.', ['title' => $assessment->title]));

        return back();
    }

    private function builder(?Assessment $exam): Response
    {
        return Inertia::render('Admin/Exams/Edit', [
            'exam' => $exam ? new ExamResource($exam) : null,
            'categories' => CategoryResource::collection(Category::topLevel()->with('children')->orderBy('sort_order')->get()),
            'skills' => Skill::query()->orderBy('name')->get(['id', 'name', 'category_id']),
            'limits' => ['minOptions' => SaveExamRequest::MIN_OPTIONS, 'maxOptions' => SaveExamRequest::MAX_OPTIONS],
            'routes' => [
                'index' => route('admin.exams.index'),
                'save' => $exam ? route('admin.exams.update', $exam) : route('admin.exams.store'),
            ],
        ]);
    }

    /**
     * Write the exam and replace its questions with the ones from the builder.
     */
    private function save(Assessment $exam, SaveExamRequest $request): Assessment
    {
        $exam->fill([
            ...$request->safe()->only(['title', 'description', 'category_id', 'skill_id', 'time_limit_minutes', 'total_score', 'pass_score']),
            'is_active' => $request->boolean('is_active', true),
        ])->save();

        $exam->questions()->delete();

        foreach (array_values($request->validated('questions')) as $position => $data) {
            $question = $exam->questions()->create([
                'body' => $data['body'],
                'hint' => $data['hint'] ?? null,
                'sort_order' => $position + 1,
            ]);

            foreach (array_values($data['options']) as $index => $option) {
                $question->options()->create([
                    'body' => $option['body'],
                    'is_correct' => $index === (int) $data['correct'],
                    'sort_order' => $index + 1,
                ]);
            }
        }

        return $exam;
    }
}
