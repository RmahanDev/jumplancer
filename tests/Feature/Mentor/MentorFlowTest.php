<?php

namespace Tests\Feature\Mentor;

use App\Enums\ExperienceLevel;
use App\Enums\MentorshipProgramStatus;
use App\Enums\MentorshipSessionStatus;
use App\Enums\ProposalStatus;
use App\Enums\TicketStatus;
use App\Models\LearningContent;
use App\Models\MentorshipProgram;
use App\Models\MentorshipSession;
use App\Models\Proposal;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class MentorFlowTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $mentor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->mentor = $this->mentor();
        $this->actingAs($this->mentor);
    }

    public function test_a_mentor_takes_a_ticket_from_the_queue_works_on_it_and_closes_it(): void
    {
        $requester = $this->freelancer();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id, 'subject' => 'گیر کردم در لاراول']);
        Ticket::factory()->create(['requester_id' => $requester->id, 'assigned_mentor_id' => $this->mentor()->id, 'status' => TicketStatus::Assigned]);

        $this->get(route('mentor.tickets.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'queue')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.id', $ticket->id)
                ->where('counts', ['queue' => 1, 'mine' => 0]));

        // A ticket has to be taken before work starts on it.
        $this->put(route('mentor.tickets.update', $ticket), ['action' => 'start'])
            ->assertSessionHasErrors(['action' => 'این تغییر برای این تیکت مجاز نیست.']);

        $this->put(route('mentor.tickets.update', $ticket), ['action' => 'take'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', "تیکت حالا با توست. به {$requester->name} سلام کن!");
        $this->assertSame($this->mentor->id, $ticket->fresh()->assigned_mentor_id);
        $this->assertSame(TicketStatus::Assigned, $ticket->fresh()->status);

        $this->get(route('mentor.tickets.index', ['tab' => 'mine']))
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('counts', ['queue' => 0, 'mine' => 1]));

        $this->put(route('mentor.tickets.update', $ticket), ['action' => 'take'])->assertSessionHasErrors('action');
        $this->put(route('mentor.tickets.update', $ticket), ['action' => 'start'])
            ->assertInertiaFlash('toast.message', 'تیکت «گیر کردم در لاراول» در حال پیگیری است.');
        $this->put(route('mentor.tickets.update', $ticket), ['action' => 'close'])
            ->assertInertiaFlash('toast.message', 'تیکت «گیر کردم در لاراول» بسته شد.');

        $this->assertSame(TicketStatus::Closed, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
        $this->put(route('mentor.tickets.update', $ticket), ['action' => 'archive'])->assertSessionHasErrors('action');
    }

    public function test_tickets_of_other_mentors_are_invisible(): void
    {
        $taken = Ticket::factory()->create([
            'requester_id' => $this->freelancer()->id,
            'assigned_mentor_id' => $this->mentor()->id,
            'status' => TicketStatus::Assigned,
        ]);

        $this->put(route('mentor.tickets.update', $taken), ['action' => 'take'])->assertNotFound();
        $this->put(route('mentor.tickets.update', $taken), ['action' => 'close'])->assertNotFound();

        $this->assertSame(TicketStatus::Assigned, $taken->fresh()->status);
    }

    public function test_the_first_programs_of_a_beginner_are_free_mentorships(): void
    {
        $beginner = $this->freelancer();
        [$first, $second, $third] = $this->takenTickets($beginner, 3);

        $this->post(route('mentor.programs.store'), $this->programPayload($first))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'برنامه شروع شد. این یکی از منتورینگ‌های رایگانِ این تازه‌کار است.');

        $program = MentorshipProgram::firstWhere('ticket_id', $first->id);
        $this->assertTrue($program->is_free_mentorship);
        $this->assertNull($program->price);
        $this->assertSame($beginner->id, $program->mentee_id);
        $this->assertSame(TicketStatus::InProgress, $first->fresh()->status);

        $this->post(route('mentor.programs.store'), $this->programPayload($second))->assertSessionHasNoErrors();
        $this->assertSame(2, $beginner->freelancerProfile()->first()->free_mentorships_used);

        // The allowance (platform setting "beginner_free_mentorships") is used up.
        $this->post(route('mentor.programs.store'), $this->programPayload($third))
            ->assertInertiaFlash('toast.message', 'برنامه‌ی منتورینگ شروع شد.');
        $paid = MentorshipProgram::firstWhere('ticket_id', $third->id);
        $this->assertFalse($paid->is_free_mentorship);
        $this->assertSame(800_000, $paid->price);
        $this->assertSame(2, $beginner->freelancerProfile()->first()->free_mentorships_used);
    }

    public function test_programs_need_an_open_ticket_that_belongs_to_the_mentor(): void
    {
        $junior = $this->freelancer(ExperienceLevel::Junior);
        [$ticket] = $this->takenTickets($junior, 1);
        $queued = Ticket::factory()->create(['requester_id' => $junior->id]);
        $closed = Ticket::factory()->create(['requester_id' => $junior->id, 'assigned_mentor_id' => $this->mentor->id, 'status' => TicketStatus::Closed]);

        $this->post(route('mentor.programs.store'), $this->programPayload($queued))->assertSessionHasErrors('ticket_id');
        $this->post(route('mentor.programs.store'), $this->programPayload($closed))
            ->assertSessionHasErrors(['ticket_id' => 'این تیکت بسته شده است.']);
        $this->post(route('mentor.programs.store'), [...$this->programPayload($ticket), 'track' => 'student'])->assertSessionHasErrors('track');

        // Juniors are past the free mentorships.
        $this->post(route('mentor.programs.store'), $this->programPayload($ticket))->assertSessionHasNoErrors();
        $this->assertFalse(MentorshipProgram::sole()->is_free_mentorship);
    }

    public function test_the_mentor_updates_and_ends_their_programs(): void
    {
        $program = $this->program();
        $other = MentorshipProgram::factory()->create(['mentor_id' => $this->mentor()->id, 'mentee_id' => $this->freelancer()->id]);

        $this->put(route('mentor.programs.update', $program), ['status' => 'completed', 'goal' => 'اولین پروژه تحویل شد'])
            ->assertInertiaFlash('toast.message', 'برنامه به‌روز شد.');
        $this->assertSame(MentorshipProgramStatus::Completed, $program->fresh()->status);
        $this->assertNotNull($program->fresh()->ended_at);

        $this->get(route('mentor.programs.index', ['status' => 'completed']))
            ->assertInertia(fn (Assert $page) => $page->has('programs.data', 1)->where('programs.data.0.id', $program->id));
        $this->get(route('mentor.programs.index', ['status' => 'active']))
            ->assertInertia(fn (Assert $page) => $page->has('programs.data', 0));

        $this->put(route('mentor.programs.update', $program), ['status' => 'active', 'goal' => 'ادامه می‌دهیم'])->assertSessionHasNoErrors();
        $this->assertNull($program->fresh()->ended_at);

        $this->put(route('mentor.programs.update', $other), ['status' => 'cancelled', 'goal' => 'x'])->assertNotFound();
    }

    public function test_sessions_are_planned_ahead_and_only_upcoming_ones_can_be_deleted(): void
    {
        $program = $this->program();
        $payload = ['scheduled_at' => now()->addDays(2)->setTime(18, 0)->toDateTimeString(), 'duration_minutes' => 45, 'session_type' => 'technical', 'meeting_link' => 'https://meet.example.com/jl'];

        $this->post(route('mentor.sessions.store', $program), [...$payload, 'scheduled_at' => now()->subHour()->toDateTimeString()])
            ->assertSessionHasErrors('scheduled_at');
        $this->post(route('mentor.sessions.store', $program), [...$payload, 'duration_minutes' => 5])->assertSessionHasErrors('duration_minutes');

        $this->post(route('mentor.sessions.store', $program), $payload)
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'جلسه برنامه‌ریزی شد.');
        $session = $program->sessions()->sole();
        $this->assertSame(MentorshipSessionStatus::Scheduled, $session->status);

        $this->get(route('mentor.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.sessions_week', 1)->where('upcoming.0.id', $session->id));

        $this->put(route('mentor.sessions.update', $session), [...$payload, 'status' => 'done', 'mentor_notes' => 'روی تست‌نویسی کار کند.'])
            ->assertInertiaFlash('toast.message', 'جلسه به‌روز شد.');
        $this->assertSame('روی تست‌نویسی کار کند.', $session->fresh()->mentor_notes);

        $this->delete(route('mentor.sessions.destroy', $session))
            ->assertSessionHasErrors(['session' => 'فقط جلسه‌های برنامه‌ریزی‌شده قابل حذف‌اند.']);

        $upcoming = MentorshipSession::factory()->create(['program_id' => $program->id]);
        $this->delete(route('mentor.sessions.destroy', $upcoming))->assertInertiaFlash('toast.message', 'جلسه حذف شد.');
        $this->assertModelMissing($upcoming);

        $program->update(['status' => MentorshipProgramStatus::Paused]);
        $this->post(route('mentor.sessions.store', $program), $payload)
            ->assertSessionHasErrors(['scheduled_at' => 'جلسه فقط در برنامه‌ی فعال برنامه‌ریزی می‌شود.']);
    }

    public function test_sessions_of_other_mentors_cannot_be_touched(): void
    {
        $foreign = MentorshipProgram::factory()->create(['mentor_id' => $this->mentor()->id, 'mentee_id' => $this->freelancer()->id]);
        $session = MentorshipSession::factory()->create(['program_id' => $foreign->id]);

        $this->post(route('mentor.sessions.store', $foreign), ['scheduled_at' => now()->addDay()->toDateTimeString(), 'duration_minutes' => 30, 'session_type' => 'review'])->assertNotFound();
        $this->put(route('mentor.sessions.update', $session), ['status' => 'cancelled', 'scheduled_at' => now()->toDateTimeString(), 'duration_minutes' => 30, 'session_type' => 'review'])->assertNotFound();
        $this->delete(route('mentor.sessions.destroy', $session))->assertNotFound();

        $this->assertModelExists($session);
    }

    public function test_mentors_coach_proposals_on_supervised_projects_and_of_their_mentees(): void
    {
        $employer = $this->employer();
        $supervised = $this->openProject($employer, ['mentor_id' => $this->mentor->id]);
        $unsupervised = $this->openProject($employer);
        $mentee = $this->freelancer();
        $this->program($mentee);

        $onSupervised = Proposal::factory()->create(['project_id' => $supervised->id, 'freelancer_id' => $this->freelancer()->id]);
        $byMentee = Proposal::factory()->create(['project_id' => $unsupervised->id, 'freelancer_id' => $mentee->id]);
        $stranger = Proposal::factory()->create(['project_id' => $unsupervised->id, 'freelancer_id' => $this->freelancer()->id]);

        $this->get(route('mentor.reviews.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 2)
                ->where('counts', ['pending' => 2, 'reviewed' => 0])
                ->where('proposals.data', fn ($proposals) => collect($proposals)->pluck('id')->sort()->values()->all() === [$onSupervised->id, $byMentee->id]));

        $this->put(route('mentor.reviews.update', $onSupervised), ['mentor_feedback' => 'کوتاه'])->assertSessionHasErrors('mentor_feedback');
        $this->put(route('mentor.reviews.update', $onSupervised), ['mentor_feedback' => 'نمونه‌کار مرتبط را اول بیاور و زمان تحویل را واقع‌بینانه بنویس.'])
            ->assertInertiaFlash('toast.message', "بازخوردت برای {$onSupervised->freelancer->name} ارسال شد.");
        $this->assertSame($this->mentor->id, $onSupervised->fresh()->mentor_reviewed_by);
        $this->assertSame(ProposalStatus::Pending, $onSupervised->fresh()->status);

        $this->put(route('mentor.reviews.update', $stranger), ['mentor_feedback' => 'این پیشنهاد به من ربطی ندارد.'])->assertNotFound();

        $this->get(route('mentor.reviews.index', ['tab' => 'reviewed']))
            ->assertInertia(fn (Assert $page) => $page->has('proposals.data', 1)->where('counts', ['pending' => 1, 'reviewed' => 1]));

        // The dashboard card, the sidebar badge and the reviews page count the same proposals.
        $this->get(route('mentor.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.pending_reviews', 1)
            ->where('panel.navigation', fn ($items) => collect($items)->firstWhere('key', 'mentor.reviews.index')['badge'] === 1));
    }

    public function test_mentors_publish_and_manage_only_their_own_learning_content(): void
    {
        $this->post(route('mentor.contents.store'), [
            'title' => 'چک‌لیست تحویل پروژه',
            'content_type' => 'checklist',
            'audience' => 'freelancer',
            'purpose' => 'technical',
            'body' => "۱. تست‌ها سبز باشند\n۲. README به‌روز باشد",
            'is_published' => true,
        ])->assertInertiaFlash('toast.message', '«چک‌لیست تحویل پروژه» ذخیره شد.');

        $content = LearningContent::sole();
        $this->assertSame($this->mentor->id, $content->author_id);
        $publishedAt = $content->published_at;
        $this->assertNotNull($publishedAt);

        $this->travel(2)->days();
        $this->put(route('mentor.contents.update', $content), [
            'title' => 'چک‌لیست تحویل پروژه',
            'content_type' => 'checklist',
            'audience' => 'all',
            'purpose' => 'technical',
            'body' => $content->body,
            'is_published' => true,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($publishedAt->equalTo($content->fresh()->published_at), 'Re-saving keeps the first publication date.');

        $this->put(route('mentor.contents.update', $content), ['title' => 'پیش‌نویس', 'content_type' => 'video', 'audience' => 'all', 'purpose' => 'motivational'])
            ->assertSessionHasErrors('body');

        $foreign = LearningContent::factory()->create(['author_id' => $this->mentor()->id]);
        $this->get(route('mentor.contents.index'))->assertInertia(fn (Assert $page) => $page->has('contents.data', 1)->where('contents.data.0.id', $content->id));
        $this->put(route('mentor.contents.update', $foreign), ['title' => 'x', 'content_type' => 'article', 'audience' => 'all', 'purpose' => 'technical', 'body' => 'y'])->assertNotFound();
        $this->delete(route('mentor.contents.destroy', $foreign))->assertNotFound();

        $this->delete(route('mentor.contents.destroy', $content))->assertInertiaFlash('toast.message', '«چک‌لیست تحویل پروژه» حذف شد.');
        $this->assertModelMissing($content);
        $this->assertModelExists($foreign);
    }

    public function test_the_dashboard_summarizes_the_mentors_work(): void
    {
        $requester = $this->freelancer();
        Ticket::factory()->count(2)->create(['requester_id' => $requester->id]);
        $this->takenTickets($requester, 1);
        $program = $this->program($requester);
        MentorshipSession::factory()->create(['program_id' => $program->id, 'scheduled_at' => now()->subDays(3), 'status' => MentorshipSessionStatus::Done, 'mentee_rating' => 4]);
        MentorshipSession::factory()->create(['program_id' => $program->id, 'scheduled_at' => now()->subDays(10), 'status' => MentorshipSessionStatus::Done, 'mentee_rating' => 5]);

        $this->get(route('mentor.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mentor/Dashboard')
                ->where('stats.queue', 2)
                ->where('stats.my_tickets', 1)
                ->where('stats.active_programs', 1)
                ->where('stats.avg_rating', 4.5)
                ->has('recentTickets', 3)
                ->has('sessionDates', 2)
                ->where('profile.is_verified', true));
    }

    /**
     * @return list<Ticket>
     */
    private function takenTickets(User $requester, int $count): array
    {
        return Ticket::factory()->count($count)->create([
            'requester_id' => $requester->id,
            'assigned_mentor_id' => $this->mentor->id,
            'status' => TicketStatus::Assigned,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function programPayload(Ticket $ticket): array
    {
        return ['ticket_id' => $ticket->id, 'track' => 'freelancer', 'goal' => 'گرفتن اولین پروژه تا پایان ماه', 'price' => 800_000];
    }

    private function program(?User $mentee = null): MentorshipProgram
    {
        return MentorshipProgram::factory()->create([
            'mentor_id' => $this->mentor->id,
            'mentee_id' => ($mentee ?? $this->freelancer())->id,
        ]);
    }
}
