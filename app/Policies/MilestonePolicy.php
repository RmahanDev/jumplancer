<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MilestonePolicy
{
    /**
     * The employer funds and releases the milestone.
     */
    public function manage(User $user, Milestone $milestone): Response
    {
        return $milestone->contract?->employer_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * The hired freelancer submits the delivered work.
     */
    public function submit(User $user, Milestone $milestone): Response
    {
        return $milestone->contract?->freelancer_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
