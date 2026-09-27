<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectReviewController extends Controller
{
    /**
     * Publish a project from the review queue, or return it to the employer with a note.
     */
    public function __invoke(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return'])],
            'review_note' => ['nullable', 'required_if:decision,return', 'string', 'max:500'],
        ]);

        if ($project->status !== ProjectStatus::PendingReview) {
            throw ValidationException::withMessages(['decision' => __('This project is not waiting for review.')]);
        }

        if ($validated['decision'] === 'approve') {
            $project->forceFill(['status' => ProjectStatus::Open, 'published_at' => now(), 'review_note' => null])->save();
            $this->toast(__('Project ":title" is now live.', ['title' => $project->title]));
        } else {
            $project->forceFill(['status' => ProjectStatus::Draft, 'review_note' => $validated['review_note']])->save();
            $this->toast(__('Project ":title" was returned to the employer.', ['title' => $project->title]), 'info');
        }

        return back();
    }
}
