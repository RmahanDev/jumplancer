<?php

namespace Tests\Feature\Freelancer;

use App\Enums\ExperienceLevel;
use App\Enums\FreelancerFieldStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ModerationStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Models\Contract;
use App\Models\Milestone;
use App\Models\PortfolioItem;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class FreelancerFlowTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    private function letter(): string
    {
        return 'سلام، این پروژه را با دقت انجام می‌دهم و هر مرحله را با گزارش کامل تحویل می‌دهم.';
    }

    public function test_the_first_field_below_the_exam_level_is_active_at_once(): void
    {
        $freelancer = $this->freelancer(activeField: null);

        $this->actingAs($freelancer)
            ->post(route('freelancer.fields.store'), ['category_id' => $this->category('programming-tech')->id, 'claimed_level' => 'junior'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'حوزه فعال شد: حالا می‌توانی برای پروژه‌هایش پیشنهاد بفرستی.');

        $field = $freelancer->freelancerFields()->sole();
        $this->assertTrue($field->is_primary);
        $this->assertSame(FreelancerFieldStatus::Active, $field->status);
        $this->assertFalse($field->exam_required);
    }

    public function test_senior_claims_and_extra_fields_need_an_exam(): void
    {
        $freelancer = $this->freelancer(activeField: null);
        $this->actingAs($freelancer);

        $this->post(route('freelancer.fields.store'), ['category_id' => $this->category('programming-tech')->id, 'claimed_level' => 'senior'])->assertSessionHasNoErrors();
        $first = $freelancer->freelancerFields()->sole();
        $this->assertSame(FreelancerFieldStatus::PendingExam, $first->status);
        $this->assertFalse($first->exam_fee_required, 'the first exam is free');

        $this->post(route('freelancer.fields.store'), ['category_id' => $this->category('design-creative')->id, 'claimed_level' => 'beginner'])->assertSessionHasNoErrors();
        $extra = $freelancer->freelancerFields()->where('category_id', $this->category('design-creative')->id)->sole();
        $this->assertTrue($extra->exam_required);
        $this->assertTrue($extra->exam_fee_required);

        $this->post(route('freelancer.fields.store'), ['category_id' => $this->category('design-creative')->id, 'claimed_level' => 'beginner'])
            ->assertSessionHasErrors(['category_id' => 'این حوزه را قبلاً ثبت کرده‌ای.']);
        $this->post(route('freelancer.fields.store'), ['category_id' => $this->category('web-development')->id, 'claimed_level' => 'beginner'])
            ->assertSessionHasErrors('category_id');

        $this->delete(route('freelancer.fields.destroy', $first))->assertSessionHasErrors(['field' => 'حوزه‌ی اصلی‌ات قابل حذف نیست.']);
        $this->delete(route('freelancer.fields.destroy', $extra))->assertSessionHasNoErrors();
        $this->assertModelMissing($extra);
    }

    public function test_freelancers_bid_on_open_projects_of_their_active_fields(): void
    {
        $freelancer = $this->freelancer();
        $project = $this->openProject($this->employer());

        $this->actingAs($freelancer)
            ->get(route('freelancer.projects.index'))
            ->assertInertia(fn (Assert $page) => $page->has('projects.data', 1)->where('projects.data.0.has_proposed', false));

        $this->post(route('freelancer.proposals.store'), [
            'project_id' => $project->id,
            'cover_letter' => $this->letter(),
            'proposed_price' => 6_000_000,
            'delivery_days' => 14,
        ])->assertSessionHasNoErrors()->assertInertiaFlash('toast.type', 'success');

        $proposal = Proposal::sole();
        $this->assertSame($freelancer->id, $proposal->freelancer_id);
        $this->assertSame(ProposalStatus::Pending, $proposal->status);

        $this->get(route('freelancer.projects.index'))->assertInertia(fn (Assert $page) => $page->where('projects.data.0.has_proposed', true));

        $this->post(route('freelancer.proposals.store'), [
            'project_id' => $project->id, 'cover_letter' => $this->letter(), 'proposed_price' => 5_000_000, 'delivery_days' => 10,
        ])->assertSessionHasErrors(['project_id' => 'برای این پروژه قبلاً پیشنهاد فرستاده‌ای.']);
    }

    public function test_the_marketplace_rules_block_invalid_proposals(): void
    {
        $freelancer = $this->freelancer(activeField: 'design-creative');
        $webProject = $this->openProject($this->employer());
        $closedProject = $this->openProject($this->employer(), ['status' => ProjectStatus::InProgress]);
        $this->actingAs($freelancer);

        $payload = fn ($project) => ['project_id' => $project->id, 'cover_letter' => $this->letter(), 'proposed_price' => 5_000_000, 'delivery_days' => 7];

        $this->post(route('freelancer.proposals.store'), $payload($webProject))
            ->assertSessionHasErrors(['project_id' => 'برای پیشنهاد دادن روی پروژه‌های این حوزه، اول آن را در «حوزه‌های کاری» فعال کن.']);
        $this->post(route('freelancer.proposals.store'), $payload($closedProject))
            ->assertSessionHasErrors(['project_id' => 'این پروژه پیشنهاد جدید نمی‌پذیرد.']);
        $this->post(route('freelancer.proposals.store'), [...$payload($webProject), 'cover_letter' => 'کوتاه'])
            ->assertSessionHasErrors('cover_letter');

        $this->assertSame(0, Proposal::count());
    }

    public function test_pending_proposals_are_edited_or_withdrawn_by_their_author_only(): void
    {
        $freelancer = $this->freelancer();
        $proposal = Proposal::factory()->create(['project_id' => $this->openProject($this->employer())->id, 'freelancer_id' => $freelancer->id]);

        $this->actingAs($this->freelancer())
            ->put(route('freelancer.proposals.update', $proposal), ['cover_letter' => $this->letter(), 'proposed_price' => 1_000, 'delivery_days' => 1])
            ->assertNotFound();

        $this->actingAs($freelancer)
            ->put(route('freelancer.proposals.update', $proposal), ['cover_letter' => $this->letter(), 'proposed_price' => 7_500_000, 'delivery_days' => 21])
            ->assertSessionHasNoErrors();
        $this->assertSame(7_500_000, $proposal->fresh()->proposed_price);

        $this->delete(route('freelancer.proposals.destroy', $proposal))->assertSessionHasNoErrors();
        $this->assertSame(ProposalStatus::Withdrawn, $proposal->fresh()->status);

        $this->put(route('freelancer.proposals.update', $proposal), ['cover_letter' => $this->letter(), 'proposed_price' => 1_000, 'delivery_days' => 1])
            ->assertSessionHasErrors(['cover_letter' => 'فقط پیشنهادهای در انتظار پاسخ قابل ویرایش‌اند.']);
        $this->delete(route('freelancer.proposals.destroy', $proposal))
            ->assertSessionHasErrors(['proposal' => 'این پیشنهاد دیگر قابل پس گرفتن نیست.']);
    }

    public function test_case_studies_keep_new_files_private_until_moderated(): void
    {
        Storage::fake('local');
        $freelancer = $this->freelancer();
        $this->actingAs($freelancer);

        $this->post(route('freelancer.portfolio.store'), [
            'title' => 'فروشگاه تمرینی',
            'category_id' => $this->category('web-development')->id,
            'description' => 'یک فروشگاه کامل با ووکامرس.',
            'duration_days' => 20,
            'is_visible' => true,
            'skills' => [$this->skillId('wordpress'), $this->skillId('php')],
            'files' => [UploadedFile::fake()->image('home.png'), UploadedFile::fake()->create('report.pdf', 120, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $item = PortfolioItem::with(['media', 'skills'])->sole();
        $this->assertCount(2, $item->media);
        $this->assertCount(2, $item->skills);
        $this->assertTrue($item->media->every(fn ($media) => $media->status === ModerationStatus::PendingReview));
        $item->media->each(fn ($media) => Storage::disk('local')->assertExists($media->file_path));

        $pending = $item->media->first();
        $this->get(route('portfolio-media.show', $pending))->assertOk();
        $this->actingAs($this->employer())->get(route('portfolio-media.show', $pending))->assertNotFound();
        $pending->forceFill(['status' => ModerationStatus::Approved])->save();
        $this->get(route('portfolio-media.show', $pending))->assertOk();

        // Remove one file and add two: 1 + 2 fits; 6 would not.
        $this->actingAs($freelancer)
            ->put(route('freelancer.portfolio.update', $item), [
                'title' => 'فروشگاه تمرینی', 'description' => 'نسخه‌ی دوم', 'remove_media' => [$pending->id],
                'files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.webp')],
            ])
            ->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($pending->file_path);
        $this->assertSame(3, $item->media()->count());

        $this->put(route('freelancer.portfolio.update', $item), [
            'title' => 'فروشگاه تمرینی', 'description' => 'نسخه‌ی سوم',
            'files' => [UploadedFile::fake()->image('c.jpg'), UploadedFile::fake()->image('d.jpg'), UploadedFile::fake()->image('e.jpg')],
        ])->assertSessionHasErrors(['files' => 'هر نمونه‌کار حداکثر ۵ فایل می‌تواند داشته باشد.']);

        $this->put(route('freelancer.portfolio.update', $item), ['title' => 'x', 'description' => 'y', 'files' => [UploadedFile::fake()->create('virus.exe', 10)]])
            ->assertSessionHasErrors('files.0');

        $this->actingAs($this->freelancer())->delete(route('freelancer.portfolio.destroy', $item))->assertNotFound();

        $this->actingAs($freelancer)->delete(route('freelancer.portfolio.destroy', $item))->assertSessionHasNoErrors();
        $this->assertModelMissing($item);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_only_funded_milestones_are_delivered_by_the_hired_freelancer(): void
    {
        $freelancer = $this->freelancer();
        $contract = $this->contractFor($freelancer);
        $funded = Milestone::factory()->create(['contract_id' => $contract->id, 'status' => MilestoneStatus::Funded]);
        $pending = Milestone::factory()->create(['contract_id' => $contract->id]);

        $this->actingAs($this->freelancer())->post(route('freelancer.milestones.submit', $funded))->assertNotFound();

        $this->actingAs($freelancer)
            ->post(route('freelancer.milestones.submit', $pending))
            ->assertSessionHasErrors(['milestone' => 'فقط مرحله‌ای که مبلغش تأمین شده قابل تحویل است.']);

        $this->post(route('freelancer.milestones.submit', $funded))->assertSessionHasNoErrors();
        $this->assertSame(MilestoneStatus::Submitted, $funded->fresh()->status);

        $this->get(route('freelancer.contracts.index'))
            ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 1)->has('contracts.data.0.milestones', 2)->where('contracts.data.0.progress.funded', 1_000_000));
    }

    public function test_dashboard_checklist_tracks_profile_steps(): void
    {
        $freelancer = $this->freelancer(ExperienceLevel::Beginner);
        $freelancer->update(['bio' => null]);

        $this->actingAs($freelancer)
            ->get(route('freelancer.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklist', fn ($steps) => collect($steps)->pluck('done', 'key')->all() === [
                    'headline' => true, 'bio' => false, 'phone' => false, 'field' => true, 'portfolio' => false, 'proposal' => false,
                ])
                ->where('stats.level', 'beginner'));
    }

    private function skillId(string $slug): int
    {
        return Skill::where('slug', $slug)->value('id');
    }

    private function contractFor(User $freelancer): Contract
    {
        $project = $this->openProject($this->employer(), ['status' => ProjectStatus::InProgress]);
        $proposal = Proposal::factory()->create(['project_id' => $project->id, 'freelancer_id' => $freelancer->id, 'status' => ProposalStatus::Accepted]);

        return Contract::factory()->create(['proposal_id' => $proposal->id]);
    }
}
