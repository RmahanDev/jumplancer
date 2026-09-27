<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProjectPublicationController extends Controller
{
    /**
     * Send a draft for admin review, using a free posting or plan quota the first time.
     */
    public function __invoke(Project $project, ProjectPostingService $posting): RedirectResponse
    {
        Gate::authorize('publish', $project);

        $posting->submitForReview($project);

        $this->toast(__('Project ":title" was sent for review. It goes live once approved.', ['title' => $project->title]));

        return back();
    }
}
