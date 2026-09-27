<?php

namespace App\Http\Controllers\Freelancer;

use App\Enums\MilestoneStatus;
use App\Http\Controllers\Controller;
use App\Models\Milestone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MilestoneSubmissionController extends Controller
{
    /**
     * Deliver a funded milestone; the employer then approves and releases the payment.
     */
    public function __invoke(Milestone $milestone): RedirectResponse
    {
        Gate::authorize('submit', $milestone);

        if ($milestone->status !== MilestoneStatus::Funded) {
            throw ValidationException::withMessages(['milestone' => __('Only funded milestones can be delivered.')]);
        }

        $milestone->update(['status' => MilestoneStatus::Submitted]);

        $this->toast(__('Milestone ":title" was delivered. The employer has been asked to review it.', ['title' => $milestone->title]));

        return back();
    }
}
