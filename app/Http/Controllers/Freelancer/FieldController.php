<?php

namespace App\Http\Controllers\Freelancer;

use App\Enums\AttemptStatus;
use App\Enums\ExperienceLevel;
use App\Enums\FreelancerFieldStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\FreelancerFieldResource;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Category;
use App\Models\FreelancerField;
use App\Models\PlatformSetting;
use App\Models\Skill;
use App\Models\User;
use App\Services\SkillExams;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Work fields (top-level categories) and the skill exams inside them. A freelancer can only bid inside
 * an active field (v4 rules 3 and 5); passing a skill exam shows the skill as verified on the profile.
 */
class FieldController extends Controller
{
    public function __construct(private readonly SkillExams $exams) {}

    public function index(Request $request): Response
    {
        $freelancer = $request->user();
        $this->exams->closeExpired($freelancer);

        $fields = $freelancer->freelancerFields()->with('category')->orderByDesc('is_primary')->oldest()->get();

        return Inertia::render('Freelancer/Fields/Index', [
            'fields' => FreelancerFieldResource::collection($fields),
            'exams' => $this->examsOf($freelancer, $fields),
            'skills' => $freelancer->skills()->wherePivot('is_verified', true)->orderBy('name')->get(['skills.id', 'skills.name'])
                ->map(fn (Skill $skill): array => ['id' => $skill->id, 'name' => $skill->name])->values(),
            'categories' => CategoryResource::collection(Category::topLevel()->orderBy('sort_order')->get()),
            'levels' => array_column(ExperienceLevel::cases(), 'value'),
            'rules' => [
                'exam_from_level' => $this->examLevel()->value,
                'extra_field_fee' => PlatformSetting::firstWhere('setting_key', 'extra_field_exam_fee')?->typed_value,
            ],
            'routes' => [
                'store' => route('freelancer.fields.store'),
                'destroy' => route('freelancer.fields.destroy', ':id'),
                'start' => route('freelancer.exams.start', ':id'),
                'attempt' => route('freelancer.attempts.show', ':id'),
                'wallet' => route('wallet.show'),
            ],
        ]);
    }

    /**
     * An exam is required at or above the configured level, and for every field after the first;
     * only the first field's exam is free. Without an exam the field is active immediately.
     */
    public function store(Request $request): RedirectResponse
    {
        $freelancer = $request->user();

        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                Rule::unique('freelancer_fields')->where('freelancer_id', $freelancer->id),
            ],
            'claimed_level' => ['required', Rule::enum(ExperienceLevel::class)],
        ], [
            'category_id.unique' => __('You already registered this field.'),
        ]);

        $isFirst = ! $freelancer->freelancerFields()->exists();
        $examRequired = ! $isFirst || ExperienceLevel::from($validated['claimed_level'])->isAtLeast($this->examLevel());

        $field = $freelancer->freelancerFields()->create([
            'category_id' => $validated['category_id'],
            'claimed_level' => $validated['claimed_level'],
            'is_primary' => $isFirst,
            'exam_required' => $examRequired,
            'exam_fee_required' => ! $isFirst,
            'status' => $examRequired ? FreelancerFieldStatus::PendingExam : FreelancerFieldStatus::Active,
            'verified_at' => $examRequired ? null : now(),
        ]);

        $this->toast($field->status === FreelancerFieldStatus::Active
            ? __('The field is active: you can send proposals for its projects now.')
            : __('The field was registered. It becomes active after you pass its entry exam.'));

        return back();
    }

    /**
     * The primary field stays; others can be removed.
     */
    public function destroy(FreelancerField $freelancerField): RedirectResponse
    {
        Gate::authorize('delete', $freelancerField);

        if ($freelancerField->is_primary) {
            throw ValidationException::withMessages(['field' => __('Your primary field cannot be removed.')]);
        }

        $freelancerField->delete();

        $this->toast(__('The field was removed.'));

        return back();
    }

    /**
     * Active skill exams of the registered fields (the exam's category is the field or one of its
     * sub-categories), with the fee and the freelancer's own attempts.
     *
     * @param  Collection<int, FreelancerField>  $fields
     * @return list<array<string, mixed>>
     */
    private function examsOf(User $freelancer, Collection $fields): array
    {
        if ($fields->isEmpty()) {
            return [];
        }

        $fieldOfCategory = Category::query()
            ->whereIn('id', $fields->pluck('category_id'))
            ->orWhereIn('parent_id', $fields->pluck('category_id'))
            ->get(['id', 'parent_id'])
            ->mapWithKeys(fn (Category $category): array => [$category->id => $category->parent_id ?? $category->id]);

        $exams = Assessment::query()
            ->where('is_active', true)
            ->whereIn('category_id', $fieldOfCategory->keys())
            ->whereHas('questions')
            ->with('skill')
            ->withCount('questions')
            ->orderBy('title')
            ->get();

        $attempts = $freelancer->assessmentAttempts()->whereIn('assessment_id', $exams->pluck('id'))->latest('id')->get()->groupBy('assessment_id');
        $fees = $fields->mapWithKeys(fn (FreelancerField $field): array => [$field->category_id => $this->exams->feeFor($field)]);

        return $exams->map(function (Assessment $exam) use ($attempts, $fieldOfCategory, $fees): array {
            $fieldId = $fieldOfCategory[$exam->category_id];
            $mine = $attempts->get($exam->id, collect());
            $last = $mine->first(fn (AssessmentAttempt $attempt): bool => $attempt->status !== AttemptStatus::InProgress);

            return [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'field_id' => $fieldId,
                'skill' => $exam->skill ? ['id' => $exam->skill->id, 'name' => $exam->skill->name] : null,
                'questions_count' => $exam->questions_count,
                'time_limit_minutes' => $exam->time_limit_minutes,
                'total_score' => $exam->total_score,
                'pass_score' => $exam->pass_score,
                'fee' => $fees[$fieldId] ?? null,
                'passed' => $mine->contains(fn (AssessmentAttempt $attempt): bool => $attempt->status === AttemptStatus::Passed),
                'running_attempt_id' => $mine->firstWhere('status', AttemptStatus::InProgress)?->id,
                'attempts_count' => $mine->count(),
                'last_attempt' => $last ? ['id' => $last->id, 'status' => $last->status->value, 'score' => $last->score] : null,
            ];
        })->values()->all();
    }

    private function examLevel(): ExperienceLevel
    {
        $value = PlatformSetting::firstWhere('setting_key', 'exam_required_from_level')?->typed_value;

        return ExperienceLevel::tryFrom((string) $value) ?? ExperienceLevel::Intermediate;
    }
}
