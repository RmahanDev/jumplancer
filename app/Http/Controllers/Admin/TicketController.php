<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every mentoring request: assign a mentor or close it.
 */
class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'type' => ['nullable', Rule::enum(TicketType::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tickets = Ticket::query()
            ->with(['requester', 'assignedMentor', 'contract.project'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('ticket_type', $type))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('subject', 'like', '%'.PersianText::normalize($search).'%'))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [TicketStatus::Open->value])
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Admin/Tickets/Index', [
            'tickets' => TicketResource::collection($tickets),
            'filters' => [
                'status' => $filters['status'] ?? null,
                'type' => $filters['type'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
            'mentors' => User::role(RoleName::Mentor)
                ->withCount(['assignedTickets' => fn (Builder $query) => $query->whereIn('status', [TicketStatus::Assigned, TicketStatus::InProgress])])
                ->orderBy('name')
                ->get()
                ->map(fn (User $mentor): array => ['id' => $mentor->id, 'name' => $mentor->name, 'load' => $mentor->assigned_tickets_count]),
            'options' => [
                'statuses' => array_column(TicketStatus::cases(), 'value'),
                'types' => array_column(TicketType::cases(), 'value'),
            ],
            'routes' => [
                'update' => route('admin.tickets.update', ':id'),
            ],
        ]);
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_mentor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['required', Rule::enum(TicketStatus::class)],
        ]);

        $mentor = isset($validated['assigned_mentor_id']) ? User::find($validated['assigned_mentor_id']) : null;

        if ($mentor !== null && ! $mentor->hasRole(RoleName::Mentor)) {
            throw ValidationException::withMessages(['assigned_mentor_id' => __('The selected user is not a mentor.')]);
        }

        $status = TicketStatus::from($validated['status']);

        if ($mentor === null && in_array($status, [TicketStatus::Assigned, TicketStatus::InProgress], true)) {
            throw ValidationException::withMessages(['assigned_mentor_id' => __('Choose a mentor for an assigned ticket.')]);
        }

        $ticket->update([
            'assigned_mentor_id' => $mentor?->id,
            'status' => $status,
            'closed_at' => $status === TicketStatus::Closed ? ($ticket->closed_at ?? now()) : null,
        ]);

        $this->toast(__('Ticket ":subject" was updated.', ['subject' => $ticket->subject]));

        return back();
    }
}
