<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\AttemptStatus;
use App\Enums\RoleName;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentOption;
use App\Models\AssessmentQuestion;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ExamBuilderTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'آزمون مقدماتی لاراول',
            'description' => 'مسیرها، کنترلرها و Eloquent.',
            'category_id' => $this->category('programming-tech')->id,
            'skill_id' => Skill::where('slug', 'laravel')->sole()->id,
            'time_limit_minutes' => 15,
            'total_score' => 100,
            'pass_score' => 60,
            'is_active' => true,
            'questions' => [
                [
                    'body' => 'کدام دستور مایگریشن‌ها را اجرا می‌کند؟',
                    'hint' => 'با artisan شروع می‌شود.',
                    'options' => [['body' => 'php artisan migrate'], ['body' => 'php artisan serve'], ['body' => 'composer install'], ['body' => 'npm run dev']],
                    'correct' => 0,
                ],
                [
                    'body' => 'Eloquent چیست؟',
                    'hint' => null,
                    'options' => [['body' => 'موتور قالب'], ['body' => 'ORM لاراول']],
                    'correct' => 1,
                ],
            ],
            ...$overrides,
        ];
    }

    public function test_the_exam_builder_is_its_own_permission_for_admins_and_support_agents(): void
    {
        $this->actingAs($this->adminWith([AdminPermission::ManageContent]))->get(route('admin.exams.index'))->assertForbidden();
        $this->post(route('admin.exams.store'), $this->payload())->assertForbidden();

        $this->actingAs($this->adminWith([AdminPermission::ManageExams]))
            ->get(route('admin.exams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Exams/Index'));

        $support = $this->adminWith([AdminPermission::ManageExams]);
        $support->syncRoles([RoleName::Support->value]);

        $this->actingAs($support->refresh())->get(route('admin.exams.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Exams/Edit')
                ->where('limits', ['minOptions' => 2, 'maxOptions' => 8])
                ->has('skills'));
    }

    public function test_an_exam_is_saved_with_its_settings_questions_hints_and_correct_options(): void
    {
        $staff = $this->adminWith([AdminPermission::ManageExams]);

        $this->actingAs($staff)->post(route('admin.exams.store'), $this->payload())
            ->assertRedirect(route('admin.exams.index'))
            ->assertInertiaFlash('toast.message', 'آزمون «آزمون مقدماتی لاراول» با ۲ سؤال ثبت شد.');

        $exam = Assessment::with('questions.options')->sole();
        $this->assertSame([15, 100, 60, true, $staff->id], [$exam->time_limit_minutes, $exam->total_score, $exam->pass_score, $exam->is_active, $exam->created_by]);
        $this->assertSame(['کدام دستور مایگریشن‌ها را اجرا می‌کند؟', 'Eloquent چیست؟'], $exam->questions->pluck('body')->all());
        $this->assertSame('با artisan شروع می‌شود.', $exam->questions[0]->hint);
        $this->assertSame([4, 2], $exam->questions->map(fn (AssessmentQuestion $question) => $question->options->count())->all());
        $this->assertSame(['php artisan migrate', 'ORM لاراول'], AssessmentOption::where('is_correct', true)->orderBy('id')->pluck('body')->all());

        $this->get(route('admin.exams.edit', $exam))
            ->assertInertia(fn (Assert $page) => $page
                ->where('exam.questions.0.correct', 0)
                ->where('exam.questions.1.correct', 1)
                ->where('exam.questions.1.options.1.body', 'ORM لاراول'));
    }

    public function test_the_builder_rules(): void
    {
        $this->actingAs($this->adminWith([AdminPermission::ManageExams]));

        $this->post(route('admin.exams.store'), $this->payload(['pass_score' => 100]))
            ->assertSessionHasErrors(['pass_score' => 'حداقل نمره‌ی قبولی باید از جمع نمرات کمتر باشد.']);

        $this->post(route('admin.exams.store'), $this->payload(['questions' => []]))
            ->assertSessionHasErrors(['questions' => 'دست‌کم یک سؤال اضافه کن.']);

        $one = $this->payload()['questions'][0];
        $this->post(route('admin.exams.store'), $this->payload(['questions' => [[...$one, 'options' => [['body' => 'تنها گزینه']], 'correct' => 0]]]))
            ->assertSessionHasErrors(['questions.0.options' => 'هر سؤال دست‌کم ۲ گزینه لازم دارد.']);

        $nine = array_map(fn (int $i): array => ['body' => "گزینه {$i}"], range(1, 9));
        $this->post(route('admin.exams.store'), $this->payload(['questions' => [[...$one, 'options' => $nine, 'correct' => 0]]]))
            ->assertSessionHasErrors(['questions.0.options' => 'هر سؤال حداکثر ۸ گزینه دارد.']);

        $this->post(route('admin.exams.store'), $this->payload(['questions' => [[...$one, 'correct' => 4]]]))
            ->assertSessionHasErrors(['questions.0.correct' => 'گزینه‌ی درست این سؤال را علامت بزن.']);

        $this->post(route('admin.exams.store'), $this->payload(['questions' => [[...$one, 'correct' => null]]]))
            ->assertSessionHasErrors(['questions.0.correct' => 'گزینه‌ی درست این سؤال را علامت بزن.']);

        $this->post(route('admin.exams.store'), $this->payload(['skill_id' => Skill::where('slug', 'figma')->sole()->id]))
            ->assertSessionHasErrors(['skill_id' => 'مهارتی از همین حوزه‌ی انتخاب‌شده انتخاب کن.']);

        $this->assertSame(0, Assessment::count());
    }

    public function test_editing_replaces_the_questions_and_waits_for_running_attempts(): void
    {
        $this->actingAs($this->adminWith([AdminPermission::ManageExams]));
        $this->post(route('admin.exams.store'), $this->payload());
        $exam = Assessment::sole();

        $payload = $this->payload(['title' => 'آزمون لاراول', 'questions' => [$this->payload()['questions'][1]]]);
        $this->put(route('admin.exams.update', $exam), $payload)->assertSessionHasNoErrors();

        $this->assertSame('آزمون لاراول', $exam->fresh()->title);
        $this->assertSame(1, AssessmentQuestion::count());
        $this->assertSame(2, AssessmentOption::count());

        $attempt = AssessmentAttempt::factory()->create(['assessment_id' => $exam->id, 'status' => AttemptStatus::InProgress, 'expires_at' => now()->addMinutes(10)]);
        $this->put(route('admin.exams.update', $exam), $this->payload())
            ->assertSessionHasErrors(['exam' => 'یک فریلنسر همین حالا در این آزمون است. بعد از تمام شدن آزمونش دوباره تلاش کن.']);

        // Exams with attempts are hidden, not deleted.
        $this->delete(route('admin.exams.destroy', $exam))->assertSessionHasErrors('exam');
        $this->put(route('admin.exams.status', $exam), ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($exam->fresh()->is_active);

        $attempt->delete();
        $this->delete(route('admin.exams.destroy', $exam))->assertSessionHasNoErrors();
        $this->assertSame(0, Assessment::count());
        $this->assertSame(0, AssessmentQuestion::count(), 'questions go with the exam');
    }
}
