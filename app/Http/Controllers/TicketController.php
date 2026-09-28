<?php

namespace App\Http\Controllers;

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Resources\TicketResource;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mentoring requests of freelancers and employers (the requester's side of tickets).
 */
class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = $request->user()->tickets()
            ->with('assignedMentor')
            ->withCount('mentorshipPrograms')
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Shared/Tickets', [
            'tickets' => TicketResource::collection($tickets),
            'options' => [
                'types' => array_column(TicketType::cases(), 'value'),
                'channels' => array_column(TicketChannel::cases(), 'value'),
            ],
            'routes' => [
                'store' => route('tickets.store'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['phone_number' => PersianText::normalizeMobile($request->string('phone_number')->toString()) ?? $request->input('phone_number')]);

        $validated = $request->validate([
            'ticket_type' => ['required', Rule::enum(TicketType::class)],
            'channel' => ['required', Rule::enum(TicketChannel::class)],
            'phone_number' => ['nullable', 'required_if:channel,'.TicketChannel::Phone->value, 'regex:/^09\d{9}$/'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $request->user()->tickets()->create([...$validated, 'status' => TicketStatus::Open]);

        $this->toast(__('Your request was sent. A mentor will pick it up soon.'));

        return back();
    }
}
