<?php

namespace App\Http\Controllers\Mentor;

use App\Enums\MentorshipProgramStatus;
use App\Enums\MentorshipSessionStatus;
use App\Enums\MentorshipSessionType;
use App\Http\Controllers\Controller;
use App\Models\MentorshipProgram;
use App\Models\MentorshipSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Meetings inside a mentorship program.
 */
class SessionController extends Controller
{
    public function store(Request $request, MentorshipProgram $mentorshipProgram): RedirectResponse
    {
        Gate::authorize('update', $mentorshipProgram);

        if ($mentorshipProgram->status !== MentorshipProgramStatus::Active) {
            throw ValidationException::withMessages(['scheduled_at' => __('Sessions can only be planned in an active program.')]);
        }

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'session_type' => ['required', Rule::enum(MentorshipSessionType::class)],
            'meeting_link' => ['nullable', 'url', 'max:255'],
        ]);

        $mentorshipProgram->sessions()->create([...$validated, 'status' => MentorshipSessionStatus::Scheduled]);

        $this->toast(__('The session was scheduled.'));

        return back();
    }

    public function update(Request $request, MentorshipSession $mentorshipSession): RedirectResponse
    {
        Gate::authorize('update', $mentorshipSession->program);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(MentorshipSessionStatus::class)],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'session_type' => ['required', Rule::enum(MentorshipSessionType::class)],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'mentor_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $mentorshipSession->update($validated);

        $this->toast(__('The session was updated.'));

        return back();
    }

    /**
     * Only sessions that have not happened can be removed; past ones stay as history.
     */
    public function destroy(MentorshipSession $mentorshipSession): RedirectResponse
    {
        Gate::authorize('update', $mentorshipSession->program);

        if ($mentorshipSession->status !== MentorshipSessionStatus::Scheduled) {
            throw ValidationException::withMessages(['session' => __('Only scheduled sessions can be deleted.')]);
        }

        $mentorshipSession->delete();

        $this->toast(__('The session was deleted.'));

        return back();
    }
}
