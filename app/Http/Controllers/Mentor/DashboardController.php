<?php

namespace App\Http\Controllers\Mentor;

use App\Enums\MentorshipProgramStatus;
use App\Enums\MentorshipSessionStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MentorshipSessionResource;
use App\Http\Resources\TicketResource;
use App\Models\MentorshipSession;
use App\Models\Proposal;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $mentor = $request->user();
        $mySessions = MentorshipSession::whereHas('program', fn (Builder $query) => $query->where('mentor_id', $mentor->id));

        return Inertia::render('Mentor/Dashboard', [
            'stats' => [
                'queue' => Ticket::where('status', TicketStatus::Open)->whereNull('assigned_mentor_id')->count(),
                'my_tickets' => $mentor->assignedTickets()->whereIn('status', [TicketStatus::Assigned, TicketStatus::InProgress])->count(),
                'active_programs' => $mentor->mentorshipsAsMentor()->where('status', MentorshipProgramStatus::Active)->count(),
                'sessions_week' => (clone $mySessions)->whereBetween('scheduled_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])->count(),
                'avg_rating' => round((float) (clone $mySessions)->whereNotNull('mentee_rating')->avg('mentee_rating'), 1),
                'pending_reviews' => Proposal::query()->reviewableBy($mentor)->whereNull('mentor_reviewed_by')->count(),
            ],
            'upcoming' => MentorshipSessionResource::collection(
                (clone $mySessions)->with('program.mentee')
                    ->where('status', MentorshipSessionStatus::Scheduled)
                    ->where('scheduled_at', '>=', now()->subHour())
                    ->orderBy('scheduled_at')
                    ->limit(5)
                    ->get(),
            ),
            'recentTickets' => TicketResource::collection(
                Ticket::with('requester')
                    ->where(fn (Builder $query) => $query->where('assigned_mentor_id', $mentor->id)
                        ->orWhere(fn (Builder $queue) => $queue->where('status', TicketStatus::Open)->whereNull('assigned_mentor_id')))
                    ->latest()
                    ->latest('id')
                    ->limit(5)
                    ->get(),
            ),
            'sessionDates' => (clone $mySessions)
                ->where('scheduled_at', '>=', now()->subWeeks(8)->startOfWeek())
                ->pluck('scheduled_at')
                ->map(fn ($date): string => $date->toIso8601String())
                ->values(),
            'profile' => [
                'is_verified' => (bool) $mentor->mentorProfile?->is_verified,
                'max_mentees' => $mentor->mentorProfile?->max_mentees,
            ],
            'routes' => [
                'tickets' => route('mentor.tickets.index'),
                'programs' => route('mentor.programs.index'),
                'reviews' => route('mentor.reviews.index'),
            ],
        ]);
    }
}
