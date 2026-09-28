<?php

namespace App\Http\Controllers\Mentor;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared queue of open requests, and the tickets a mentor took.
 */
class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['queue', 'mine'])]])['tab'] ?? 'queue';
        $mentor = $request->user();

        $tickets = Ticket::query()
            ->with(['requester.freelancerProfile', 'assignedMentor', 'contract.project'])
            ->withCount('mentorshipPrograms')
            ->when($tab === 'queue', fn ($query) => $query->where('status', TicketStatus::Open)->whereNull('assigned_mentor_id'))
            ->when($tab === 'mine', fn ($query) => $query->where('assigned_mentor_id', $mentor->id))
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [TicketStatus::Closed->value])
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Mentor/Tickets/Index', [
            'tab' => $tab,
            'tickets' => TicketResource::collection($tickets),
            'counts' => [
                'queue' => Ticket::where('status', TicketStatus::Open)->whereNull('assigned_mentor_id')->count(),
                'mine' => $mentor->assignedTickets()->where('status', '!=', TicketStatus::Closed)->count(),
            ],
            'routes' => [
                'update' => route('mentor.tickets.update', ':id'),
                'startProgram' => route('mentor.programs.store'),
            ],
        ]);
    }

    /**
     * Take a ticket from the queue, start working on it, or close it.
     */
    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $action = $request->validate(['action' => ['required', Rule::in(['take', 'start', 'close'])]])['action'];
        $isMine = $ticket->assigned_mentor_id === $request->user()->id;

        match (true) {
            $action === 'take' && $ticket->assigned_mentor_id === null => $ticket->update([
                'assigned_mentor_id' => $request->user()->id,
                'status' => TicketStatus::Assigned,
            ]),
            $action === 'start' && $isMine => $ticket->update(['status' => TicketStatus::InProgress]),
            $action === 'close' && $isMine => $ticket->update(['status' => TicketStatus::Closed, 'closed_at' => now()]),
            default => throw ValidationException::withMessages(['action' => __('This ticket cannot be changed that way.')]),
        };

        $this->toast(match ($action) {
            'take' => __('The ticket is yours now. Say hello to :name!', ['name' => $ticket->requester->name]),
            'start' => __('Ticket ":subject" is in progress.', ['subject' => $ticket->subject]),
            default => __('Ticket ":subject" was closed.', ['subject' => $ticket->subject]),
        });

        return back();
    }
}
