<?php

namespace Tests\Feature\Freelancer;

use App\Enums\AttemptStatus;
use App\Enums\ExperienceLevel;
use App\Enums\FreelancerFieldStatus;
use App\Enums\TransactionType;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\PlatformSetting;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use App\Services\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SkillExamTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /**
     * An active exam with four two-option questions; the first option is always the correct one.
     */
    private function exam(string $category = 'web-development', string $skill = 'laravel'): Assessment
    {
        $exam = Assessment::create([
            'scope' => 'skill',
            'category_id' => $this->category($category)->id,
            'skill_id' => Skill::where('slug', $skill)->sole()->id,
            'title' => "آزمون {$skill}",
            'time_limit_minutes' => 10,
            'total_score' => 100,
            'pass_score' => 60,
            'is_active' => true,
        ]);

        foreach (range(1, 4) as $number) {
            $question = $exam->questions()->create(['body' => "سؤال {$number}", 'sort_order' => $number]);
            $question->options()->create(['body' => 'درست', 'is_correct' => true, 'sort_order' => 1]);
            $question->options()->create(['body' => 'غلط', 'is_correct' => false, 'sort_order' => 2]);
        }

        return $exam->load('questions.options');
    }

    /**
     * question id => option id, answering the first `$correct` questions right and the rest wrong.
     *
     * @return array<int, int>
     */
    private function answers(Assessment $exam, int $correct): array
    {
        return $exam->questions->mapWithKeys(fn ($question, $index) => [
            $question->id => $question->options->firstWhere('is_correct', $index < $correct)->id,
        ])->all();
    }

    private function start(User $freelancer, Assessment $exam): AssessmentAttempt
    {
        $this->actingAs($freelancer)->post(route('freelancer.exams.start', $exam))->assertRedirect();

        return $freelancer->assessmentAttempts()->latest('id')->firstOrFail();
    }

    public function test_the_exams_of_a_registered_field_show_on_the_fields_page(): void
    {
        $freelancer = $this->freelancer();
        $exam = $this->exam();
        $this->exam('ui-ux', 'figma');

        $this->actingAs($freelancer)->get(route('freelancer.fields.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Freelancer/Fields/Index')
                ->has('exams', 1)
                ->where('exams.0.id', $exam->id)
                ->where('exams.0.field_id', $this->category('programming-tech')->id)
                ->where('exams.0.questions_count', 4)
                ->where('exams.0.fee', 0)
                ->where('exams.0.passed', false)
                ->where('skills', []));
    }

    public function test_the_exam_page_never_sends_the_correct_options_and_saves_answers(): void
    {
        $freelancer = $this->freelancer();
        $exam = $this->exam();
        $attempt = $this->start($freelancer, $exam);

        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertTrue($attempt->expires_at->between(now()->addMinutes(9), now()->addMinutes(11)));

        $response = $this->get(route('freelancer.attempts.show', $attempt))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Freelancer/Exams/Take')
                ->has('exam.questions', 4)
                ->has('exam.questions.0.options.0', fn (Assert $option) => $option->hasAll(['id', 'body'])->missing('is_correct')));
        $this->assertStringNotContainsString('is_correct', $response->getContent());

        // Starting again returns to the running attempt.
        $this->post(route('freelancer.exams.start', $exam))->assertRedirect(route('freelancer.attempts.show', $attempt));
        $this->assertSame(1, AssessmentAttempt::count());

        $other = $this->exam('mobile-apps', 'flutter');
        $question = $exam->questions[0];
        $this->putJson(route('freelancer.attempts.answers', $attempt), ['answers' => [
            $question->id => $question->options[1]->id,
            $exam->questions[1]->id => $other->questions[0]->options[0]->id,
        ]])->assertOk();

        $this->assertSame([(string) $question->id => $question->options[1]->id], $attempt->fresh()->answers, 'options of another exam are ignored');

        $this->actingAs($this->freelancer())->get(route('freelancer.attempts.show', $attempt))->assertNotFound();
    }

    public function test_passing_verifies_the_skill_on_the_profile(): void
    {
        $freelancer = $this->freelancer();
        $exam = $this->exam();
        $attempt = $this->start($freelancer, $exam);

        $this->post(route('freelancer.attempts.submit', $attempt), ['answers' => $this->answers($exam, 3)])
            ->assertRedirect(route('freelancer.attempts.show', $attempt))
            ->assertInertiaFlash('toast.message', 'با نمره‌ی ۷۵ قبول شدی. این مهارت حالا با نشان تأییدشده در پروفایلت دیده می‌شود.');

        $attempt->refresh();
        $this->assertSame([AttemptStatus::Passed, 75, 3, true], [$attempt->status, $attempt->score, $attempt->correct_count, $attempt->passed]);
        $this->assertTrue((bool) $freelancer->skills()->where('skills.id', $exam->skill_id)->sole()->pivot->is_verified);

        $this->get(route('freelancer.attempts.show', $attempt))
            ->assertInertia(fn (Assert $page) => $page->component('Freelancer/Exams/Result')->where('attempt.status', 'passed')->where('retake', null));
        $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('profiles.freelancer.verified_skills.0.name', 'Laravel'));

        $this->post(route('freelancer.exams.start', $exam))->assertSessionHasErrors(['exam' => 'قبلاً در این آزمون قبول شده‌ای.']);
    }

    public function test_employers_see_verified_skills_on_proposals(): void
    {
        $freelancer = $this->freelancer();
        $exam = $this->exam();
        $attempt = $this->start($freelancer, $exam);
        $this->post(route('freelancer.attempts.submit', $attempt), ['answers' => $this->answers($exam, 4)]);

        $employer = $this->employer();
        Proposal::factory()->create(['project_id' => $this->openProject($employer)->id, 'freelancer_id' => $freelancer->id]);

        $this->actingAs($employer)->get(route('employer.proposals.index'))
            ->assertInertia(fn (Assert $page) => $page->where('proposals.data.0.freelancer.verified_skills.0.name', 'Laravel'));
    }

    public function test_a_failed_exam_can_be_taken_again_right_away(): void
    {
        $freelancer = $this->freelancer();
        $exam = $this->exam();
        $attempt = $this->start($freelancer, $exam);

        $this->post(route('freelancer.attempts.submit', $attempt), ['answers' => $this->answers($exam, 2)])
            ->assertInertiaFlash('toast.message', 'نمره‌ات ۵۰ شد که از حد قبولی کمتر است. همین حالا می‌توانی دوباره شرکت کنی.');

        $this->assertSame(AttemptStatus::Failed, $attempt->fresh()->status);
        $this->assertSame(0, $freelancer->skills()->count());

        $retake = $this->start($freelancer, $exam);
        $this->assertNotSame($attempt->id, $retake->id);
        $this->assertSame(AttemptStatus::InProgress, $retake->status);
    }

    public function test_leaving_the_page_warns_once_then_voids_the_attempt_and_burns_the_fee(): void
    {
        PlatformSetting::where('setting_key', 'extra_field_exam_fee')->update(['setting_value' => '150000']);
        $freelancer = $this->freelancer();
        $freelancer->freelancerFields()->create(['category_id' => $this->category('design-creative')->id, 'claimed_level' => ExperienceLevel::Beginner, 'is_primary' => false, 'status' => FreelancerFieldStatus::PendingExam]);
        app(WalletLedger::class)->deposit($freelancer, 200_000);
        $exam = $this->exam('ui-ux', 'figma');

        $attempt = $this->start($freelancer, $exam);
        $this->assertSame(150_000, $attempt->fee_amount);
        $this->assertSame(50_000, $freelancer->wallet()->first()->balance);
        $this->assertSame(-150_000, (int) $freelancer->wallet()->first()->transactions()->where('type', TransactionType::ExamFee)->sole()->amount);

        $this->postJson(route('freelancer.attempts.violation', $attempt), ['reason' => 'hidden'])
            ->assertOk()
            ->assertJson(['warnings' => 1, 'voided' => false]);

        $this->postJson(route('freelancer.attempts.violation', $attempt), ['reason' => 'blur'])
            ->assertOk()
            ->assertJson(['warnings' => 2, 'voided' => true]);

        $attempt->refresh();
        $this->assertSame([AttemptStatus::Voided, 'blur', 0], [$attempt->status, $attempt->void_reason, $attempt->score]);
        $this->assertSame(50_000, $freelancer->wallet()->first()->balance, 'the fee is not returned');

        // A voided attempt takes no more answers and cannot be submitted into a pass.
        $this->putJson(route('freelancer.attempts.answers', $attempt), ['answers' => $this->answers($exam, 4)])->assertStatus(409);
        $this->post(route('freelancer.attempts.submit', $attempt), ['answers' => $this->answers($exam, 4)]);
        $this->assertSame(AttemptStatus::Voided, $attempt->fresh()->status);

        // Retaking means paying again.
        $this->post(route('freelancer.exams.start', $exam))
            ->assertSessionHasErrors(['exam' => 'هزینه‌ی این آزمون ۱۵۰٬۰۰۰ تومان است. اول ۱۰۰٬۰۰۰ تومان کیف پولت را شارژ کن.']);
    }

    public function test_passing_an_exam_of_a_pending_field_activates_it(): void
    {
        PlatformSetting::where('setting_key', 'extra_field_exam_fee')->update(['setting_value' => '0']);
        $freelancer = $this->freelancer();
        $field = $freelancer->freelancerFields()->create(['category_id' => $this->category('design-creative')->id, 'claimed_level' => ExperienceLevel::Beginner, 'is_primary' => false, 'status' => FreelancerFieldStatus::PendingExam]);
        $exam = $this->exam('ui-ux', 'figma');

        $attempt = $this->start($freelancer, $exam);
        $this->post(route('freelancer.attempts.submit', $attempt), ['answers' => $this->answers($exam, 4)]);

        $this->assertSame(FreelancerFieldStatus::Active, $field->fresh()->status);
        $this->assertNotNull($field->fresh()->verified_at);
    }

    public function test_exams_of_unregistered_fields_or_without_a_fee_cannot_start(): void
    {
        $freelancer = $this->freelancer();
        $this->actingAs($freelancer)->post(route('freelancer.exams.start', $this->exam('ui-ux', 'figma')))
            ->assertSessionHasErrors(['exam' => 'اول حوزه‌ی این آزمون را در «حوزه‌ها و مهارت‌ها» ثبت کن.']);

        $freelancer->freelancerFields()->create(['category_id' => $this->category('design-creative')->id, 'claimed_level' => ExperienceLevel::Beginner, 'is_primary' => false, 'status' => FreelancerFieldStatus::PendingExam]);
        $this->post(route('freelancer.exams.start', Assessment::sole()))
            ->assertSessionHasErrors(['exam' => 'هزینه‌ی این آزمون هنوز تعیین نشده است. کمی بعد دوباره سر بزن.']);

        $hidden = $this->exam();
        $hidden->update(['is_active' => false]);
        $this->post(route('freelancer.exams.start', $hidden))->assertSessionHasErrors(['exam' => 'این آزمون فعلاً باز نیست.']);
        $this->assertSame(0, AssessmentAttempt::count());
    }

    public function test_an_attempt_whose_time_ran_out_is_graded_with_the_saved_answers(): void
    {
        $freelancer = $this->freelancer();
        $exam = $this->exam();
        $attempt = $this->start($freelancer, $exam);
        $this->putJson(route('freelancer.attempts.answers', $attempt), ['answers' => $this->answers($exam, 4)])->assertOk();

        $this->travel(11)->minutes();

        $this->get(route('freelancer.attempts.show', $attempt))
            ->assertInertia(fn (Assert $page) => $page->component('Freelancer/Exams/Result')->where('attempt.score', 100));
        $this->assertSame(AttemptStatus::Passed, $attempt->fresh()->status);
    }
}
