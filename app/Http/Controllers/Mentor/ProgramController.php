<?php

namespace App\Http\Controllers\Mentor;

use App\Enums\ExperienceLevel;
use App\Enums\MentorshipProgramStatus;
use App\Enums\MentorshipSessionType;
use App\Enums\MentorshipTrack;
use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MentorshipProgramResource;
use App\Models\MentorshipProgram;
use App\Models\PlatformSetting;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mentor-mentee relationships created from tickets, with their sessions.
 */
class ProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(MentorshipProgramStatus::class)]])['status'] ?? null;

        $programs = $request->user()->mentorshipsAsMentor()
            ->with(['mentee', 'ticket', 'sessions' => fn ($query) => $query->orderBy('scheduled_at')])
            ->withCount('sessions')
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [MentorshipProgramStatus::Active->value])
            ->latest('started_at')
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Mentor/Programs/Index', [
            'programs' => MentorshipProgramResource::collection($programs),
            'filters' => ['status' => $status],
            'options' => [
                'statuses' => array_column(MentorshipProgramStatus::cases(), 'value'),
                'sessionTypes' => array_column(MentorshipSessionType::cases(), 'value'),
            ],
            'routes' => [
                'update' => route('mentor.programs.update', ':id'),
                'sessionStore' => route('mentor.sessions.store', ':id'),
                'sessionUpdate' => route('mentor.sessions.update', ':id'),
                'sessionDestroy' => route('mentor.sessions.destroy', ':id'),
            ],
        ]);
    }

    /**
     * Start a program from a ticket the mentor took. Mentoring is for freelancers only. The first
     * mentorships of a beginner freelancer are free (platform setting "beginner_free_mentorships");
     * for a ticket opened by a hire, that was already decided on the contract, and the mentor
     * becomes the contract's mentor.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_id' => ['required', 'integer', Rule::exists('tickets', 'id')->where('assigned_mentor_id', $request->user()->id)],
            'goal' => ['required', 'string', 'max:2000'],
            'price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $program = DB::transaction(function () use ($request, $validated): MentorshipProgram {
            $ticket = Ticket::with(['requester.freelancerProfile', 'contract'])->lockForUpdate()->findOrFail($validated['ticket_id']);

            if ($ticket->status === TicketStatus::Closed) {
                throw ValidationException::withMessages(['ticket_id' => __('This ticket is closed.')]);
            }

            if (! $ticket->requester->hasRole(RoleName::Freelancer)) {
                throw ValidationException::withMessages(['ticket_id' => __('Mentoring programs are only for freelancers.')]);
            }

            $contract = $ticket->contract;
            $isFree = $contract !== null ? $contract->is_free_mentorship : $this->grantFreeMentorship($ticket->requester);

            $contract?->update(['mentor_id' => $request->user()->id]);

            $program = $request->user()->mentorshipsAsMentor()->create([
                'ticket_id' => $ticket->id,
                'mentee_id' => $ticket->requester_id,
                'track' => MentorshipTrack::Freelancer,
                'goal' => $validated['goal'],
                'status' => MentorshipProgramStatus::Active,
                'is_free_mentorship' => $isFree,
                // A hire's mentoring is paid through the contract's higher platform fee.
                'price' => $isFree || $contract !== null ? null : ($validated['price'] ?? null),
                'started_at' => now(),
            ]);

            $ticket->update(['status' => TicketStatus::InProgress]);

            return $program;
        });

        $this->toast($program->is_free_mentorship
            ? __('The program started. It is one of the free mentorships of this beginner.')
            : __('The program started.'));

        return back();
    }

    public function update(Request $request, MentorshipProgram $mentorshipProgram): RedirectResponse
    {
        Gate::authorize('update', $mentorshipProgram);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(MentorshipProgramStatus::class)],
            'goal' => ['required', 'string', 'max:2000'],
        ]);

        $status = MentorshipProgramStatus::from($validated['status']);
        $isOver = in_array($status, [MentorshipProgramStatus::Completed, MentorshipProgramStatus::Cancelled], true);

        $mentorshipProgram->update([
            'status' => $status,
            'goal' => $validated['goal'],
            'ended_at' => $isOver ? ($mentorshipProgram->ended_at ?? now()) : null,
        ]);

        $this->toast(__('The program was updated.'));

        return back();
    }

    /**
     * Use one of the mentee's free mentorships when they are a beginner freelancer with some left.
     */
    private function grantFreeMentorship(User $mentee): bool
    {
        $profile = $mentee->freelancerProfile;
        $allowance = (int) PlatformSetting::valueOf('beginner_free_mentorships', 2);

        if ($profile === null || $profile->level !== ExperienceLevel::Beginner || $profile->free_mentorships_used >= $allowance) {
            return false;
        }

        $profile->increment('free_mentorships_used');

        return true;
    }
}
