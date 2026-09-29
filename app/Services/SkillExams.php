<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\ExamPaymentStatus;
use App\Enums\FreelancerFieldStatus;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentOption;
use App\Models\FreelancerField;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Skill exams from the freelancer's side: who may take which exam and for how much, the running
 * attempt (answers, leaving the page), grading, and the verified skill a pass gives.
 */
class SkillExams
{
    /** Leaving the exam page this many times voids the attempt (the first time is a warning). */
    public const MAX_WARNINGS = 2;

    /** Answers that reach the server this long after the timer ran out still count (slow networks). */
    public const GRACE_SECONDS = 30;

    public const VIOLATIONS = ['hidden', 'blur', 'devtools'];

    public function __construct(private readonly WalletLedger $ledger) {}

    /**
     * The freelancer's field an exam belongs to: its category is the field or one of its sub-categories.
     */
    public function fieldFor(User $freelancer, Assessment $exam): ?FreelancerField
    {
        $exam->loadMissing('category');
        $fieldId = $exam->category?->parent_id ?? $exam->category_id;

        return $freelancer->freelancerFields()->where('category_id', $fieldId)->first();
    }

    /**
     * Exams of the primary field cost `primary_field_exam_fee` (0 = free), others `extra_field_exam_fee`.
     * Null while the fee of other fields has not been set: those exams cannot be taken yet.
     */
    public function feeFor(FreelancerField $field): ?int
    {
        if ($field->is_primary) {
            return (int) PlatformSetting::valueOf('primary_field_exam_fee', 0);
        }

        $fee = PlatformSetting::valueOf('extra_field_exam_fee');

        return $fee === null ? null : (int) $fee;
    }

    /**
     * Start an attempt (charging the fee), or return the one already running for this exam.
     *
     * @throws ValidationException
     */
    public function start(User $freelancer, Assessment $exam): AssessmentAttempt
    {
        $this->closeExpired($freelancer);

        $running = $freelancer->assessmentAttempts()->where('status', AttemptStatus::InProgress)->first();

        if ($running?->assessment_id === $exam->id) {
            return $running;
        }

        if ($running) {
            throw ValidationException::withMessages(['exam' => __('Finish the exam you already started first.')]);
        }

        if (! $exam->is_active || ! $exam->questions()->exists()) {
            throw ValidationException::withMessages(['exam' => __('This exam is not open right now.')]);
        }

        $field = $this->fieldFor($freelancer, $exam);

        if (! $field) {
            throw ValidationException::withMessages(['exam' => __('Register the field of this exam first.')]);
        }

        if ($this->hasPassed($freelancer, $exam)) {
            throw ValidationException::withMessages(['exam' => __('You already passed this exam.')]);
        }

        $fee = $this->feeFor($field);

        if ($fee === null) {
            throw ValidationException::withMessages(['exam' => __('The fee of this exam is not set yet. Try again soon.')]);
        }

        return DB::transaction(function () use ($freelancer, $exam, $field, $fee): AssessmentAttempt {
            $attempt = $freelancer->assessmentAttempts()->create([
                'assessment_id' => $exam->id,
                'freelancer_field_id' => $field->id,
                'status' => AttemptStatus::InProgress,
                'warnings' => 0,
                'answers' => [],
                'fee_amount' => $fee,
                'payment_status' => $fee > 0 ? ExamPaymentStatus::Paid : ExamPaymentStatus::Free,
                'started_at' => now(),
                'expires_at' => now()->addMinutes($exam->time_limit_minutes),
            ]);

            if ($fee > 0) {
                $this->ledger->chargeExamFee($freelancer, $attempt->setRelation('assessment', $exam), $fee);
            }

            return $attempt;
        });
    }

    public function hasPassed(User $freelancer, Assessment $exam): bool
    {
        return $freelancer->assessmentAttempts()->where('assessment_id', $exam->id)->where('status', AttemptStatus::Passed)->exists();
    }

    /**
     * Still accepting answers: running, and the timer (plus a short grace) has not run out.
     */
    public function isOpen(AssessmentAttempt $attempt): bool
    {
        return $attempt->status === AttemptStatus::InProgress
            && $attempt->expires_at !== null
            && now()->lte($attempt->expires_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /**
     * Keep the chosen options (question id => option id); options of other exams are ignored.
     *
     * @param  array<int|string, int|string|null>  $answers
     */
    public function recordAnswers(AssessmentAttempt $attempt, array $answers): void
    {
        $valid = AssessmentOption::query()
            ->whereIn('id', array_filter(array_map('intval', $answers)))
            ->whereHas('question', fn ($query) => $query->where('assessment_id', $attempt->assessment_id))
            ->pluck('question_id', 'id');

        $current = $attempt->answers ?? [];

        foreach ($answers as $question => $option) {
            if ($option === null || $option === '') {
                unset($current[(string) $question]);
            } elseif ((int) ($valid[(int) $option] ?? 0) === (int) $question) {
                $current[(string) $question] = (int) $option;
            }
        }

        $attempt->forceFill(['answers' => $current])->save();
    }

    /**
     * The freelancer left the exam page (switched tab or app, minimized, opened developer tools).
     * The first time is a warning; the second voids the attempt and the fee is not returned.
     */
    public function recordViolation(AssessmentAttempt $attempt, string $reason): AssessmentAttempt
    {
        return DB::transaction(function () use ($attempt, $reason): AssessmentAttempt {
            $attempt = AssessmentAttempt::lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== AttemptStatus::InProgress) {
                return $attempt;
            }

            $attempt->warnings = min(255, $attempt->warnings + 1);

            if ($attempt->warnings >= self::MAX_WARNINGS) {
                $attempt->forceFill([
                    'status' => AttemptStatus::Voided,
                    'void_reason' => $reason,
                    'passed' => false,
                    'score' => 0,
                    'correct_count' => 0,
                    'finished_at' => now(),
                ]);
            }

            $attempt->save();

            return $attempt;
        });
    }

    /**
     * Grade the attempt: score = correct answers / questions × total score. A pass verifies the
     * exam's skill on the freelancer's profile and activates a field that was waiting for its exam.
     */
    public function finish(AssessmentAttempt $attempt): AssessmentAttempt
    {
        return DB::transaction(function () use ($attempt): AssessmentAttempt {
            $attempt = AssessmentAttempt::lockForUpdate()->with('user')->findOrFail($attempt->id);

            if ($attempt->status !== AttemptStatus::InProgress) {
                return $attempt;
            }

            $exam = $attempt->assessment()->with('questions.options')->firstOrFail();
            $answers = $attempt->answers ?? [];
            $total = $exam->questions->count();

            $correct = $exam->questions->filter(function ($question) use ($answers): bool {
                $chosen = $answers[(string) $question->id] ?? null;

                return $chosen !== null && (bool) $question->options->firstWhere('id', (int) $chosen)?->is_correct;
            })->count();

            $score = $total > 0 ? (int) round($correct / $total * $exam->total_score) : 0;
            $passed = $score >= $exam->pass_score;

            $attempt->forceFill([
                'status' => $passed ? AttemptStatus::Passed : AttemptStatus::Failed,
                'correct_count' => $correct,
                'score' => $score,
                'passed' => $passed,
                'finished_at' => now(),
            ])->save();

            if ($passed) {
                if ($exam->skill_id) {
                    $attempt->user->skills()->syncWithoutDetaching([$exam->skill_id => ['is_verified' => true]]);
                }

                $field = $attempt->freelancerField;

                if ($field && $field->status !== FreelancerFieldStatus::Active) {
                    $field->update(['status' => FreelancerFieldStatus::Active, 'verified_at' => now()]);
                }
            }

            return $attempt;
        });
    }

    /**
     * Grade the freelancer's attempts whose time ran out while they were away.
     */
    public function closeExpired(User $freelancer): void
    {
        $freelancer->assessmentAttempts()
            ->where('status', AttemptStatus::InProgress)
            ->where('expires_at', '<', now()->subSeconds(self::GRACE_SECONDS))
            ->get()
            ->each(fn (AssessmentAttempt $attempt) => $this->finish($attempt));
    }
}
