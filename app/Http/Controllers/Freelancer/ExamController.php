<?php

namespace App\Http\Controllers\Freelancer;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentOption;
use App\Models\AssessmentQuestion;
use App\Services\SkillExams;
use App\Support\PersianText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Taking a skill exam. The exam page never receives the correct options; answers are saved as the
 * freelancer goes, and leaving the page twice (another tab or app, minimizing, developer tools)
 * voids the attempt.
 */
class ExamController extends Controller
{
    public function __construct(private readonly SkillExams $exams) {}

    public function start(Request $request, Assessment $assessment): RedirectResponse
    {
        $attempt = $this->exams->start($request->user(), $assessment);

        return to_route('freelancer.attempts.show', $attempt);
    }

    /**
     * The exam while it runs; the result once it is over.
     */
    public function show(Request $request, AssessmentAttempt $attempt): Response
    {
        $this->authorizeAttempt($request, $attempt);

        if ($attempt->status === AttemptStatus::InProgress && ! $this->exams->isOpen($attempt)) {
            $attempt = $this->exams->finish($attempt);
        }

        $exam = $attempt->assessment()->with(['skill', 'category'])->firstOrFail();

        if ($attempt->status === AttemptStatus::InProgress) {
            $exam->load('questions.options');

            return Inertia::render('Freelancer/Exams/Take', [
                'exam' => [
                    'title' => $exam->title,
                    'description' => $exam->description,
                    'skill' => $exam->skill?->name,
                    'total_score' => $exam->total_score,
                    'pass_score' => $exam->pass_score,
                    'time_limit_minutes' => $exam->time_limit_minutes,
                    'questions' => $exam->questions->map(fn (AssessmentQuestion $question): array => [
                        'id' => $question->id,
                        'body' => $question->body,
                        'hint' => $question->hint,
                        'options' => $question->options->map(fn (AssessmentOption $option): array => ['id' => $option->id, 'body' => $option->body])->values(),
                    ])->values(),
                ],
                'attempt' => [
                    'id' => $attempt->id,
                    'answers' => (object) ($attempt->answers ?? []),
                    'warnings' => $attempt->warnings,
                    'max_warnings' => SkillExams::MAX_WARNINGS,
                    'fee_amount' => $attempt->fee_amount,
                    'expires_at' => $attempt->expires_at?->toIso8601String(),
                    'now' => now()->toIso8601String(),
                ],
                'routes' => [
                    'answers' => route('freelancer.attempts.answers', $attempt),
                    'violation' => route('freelancer.attempts.violation', $attempt),
                    'submit' => route('freelancer.attempts.submit', $attempt),
                    'show' => route('freelancer.attempts.show', $attempt),
                ],
            ]);
        }

        return Inertia::render('Freelancer/Exams/Result', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'skill' => $exam->skill?->name,
                'category' => $exam->category?->name,
                'total_score' => $exam->total_score,
                'pass_score' => $exam->pass_score,
                'questions_count' => $exam->questions()->count(),
                'is_active' => $exam->is_active,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status->value,
                'score' => $attempt->score,
                'correct_count' => $attempt->correct_count,
                'warnings' => $attempt->warnings,
                'void_reason' => $attempt->void_reason,
                'fee_amount' => $attempt->fee_amount,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'finished_at' => $attempt->finished_at?->toIso8601String(),
            ],
            'retake' => $attempt->status !== AttemptStatus::Passed && $exam->is_active ? [
                'fee' => ($field = $attempt->freelancerField) ? $this->exams->feeFor($field) : null,
            ] : null,
            'routes' => [
                'retake' => route('freelancer.exams.start', $exam),
                'fields' => route('freelancer.fields.index'),
            ],
        ]);
    }

    /**
     * Autosave: question id => option id (null clears the answer).
     */
    public function answers(Request $request, AssessmentAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);

        $validated = $request->validate([
            'answers' => ['present', 'array'],
            'answers.*' => ['nullable', 'integer'],
        ]);

        if (! $this->exams->isOpen($attempt)) {
            return response()->json(['open' => false, 'redirect' => route('freelancer.attempts.show', $attempt)], 409);
        }

        $this->exams->recordAnswers($attempt, $validated['answers']);

        return response()->json(['open' => true, 'saved' => count($attempt->answers ?? [])]);
    }

    /**
     * The exam page lost focus. The first time is a warning; the second voids the attempt.
     */
    public function violation(Request $request, AssessmentAttempt $attempt): JsonResponse|HttpResponse
    {
        $this->authorizeAttempt($request, $attempt);

        $reason = $request->validate(['reason' => ['required', Rule::in(SkillExams::VIOLATIONS)]])['reason'];

        if ($attempt->status !== AttemptStatus::InProgress) {
            return response()->noContent();
        }

        $attempt = $this->exams->recordViolation($attempt, $reason);

        return response()->json([
            'warnings' => $attempt->warnings,
            'voided' => $attempt->status === AttemptStatus::Voided,
            'redirect' => route('freelancer.attempts.show', $attempt),
        ]);
    }

    public function submit(Request $request, AssessmentAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'integer'],
        ]);

        if ($attempt->status === AttemptStatus::InProgress) {
            if ($this->exams->isOpen($attempt) && ! empty($validated['answers'])) {
                $this->exams->recordAnswers($attempt, $validated['answers']);
            }

            $attempt = $this->exams->finish($attempt);

            $this->toast($attempt->status === AttemptStatus::Passed
                ? __('You passed with :score. The skill now shows as verified on your profile.', ['score' => PersianText::number($attempt->score)])
                : __('You scored :score, below the pass mark. You can take the exam again right away.', ['score' => PersianText::number($attempt->score)]),
                $attempt->status === AttemptStatus::Passed ? 'success' : 'warning');
        }

        return to_route('freelancer.attempts.show', $attempt);
    }

    private function authorizeAttempt(Request $request, AssessmentAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);
    }
}
