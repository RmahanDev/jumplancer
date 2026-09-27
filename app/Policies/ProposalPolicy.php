<?php

namespace App\Policies;

use App\Enums\MentorshipProgramStatus;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProposalPolicy
{
    /**
     * The freelancer edits their own proposal.
     */
    public function update(User $user, Proposal $proposal): Response
    {
        return $proposal->freelancer_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * The freelancer withdraws their own proposal.
     */
    public function delete(User $user, Proposal $proposal): Response
    {
        return $this->update($user, $proposal);
    }

    /**
     * The employer who posted the project shortlists, rejects or hires.
     */
    public function respond(User $user, Proposal $proposal): Response
    {
        return $proposal->project?->employer_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * A mentor reviews proposals on projects they supervise or sent by their active mentees.
     */
    public function review(User $user, Proposal $proposal): Response
    {
        if ($proposal->project?->mentor_id === $user->id) {
            return Response::allow();
        }

        $mentorsFreelancer = $user->mentorshipsAsMentor()
            ->where('mentee_id', $proposal->freelancer_id)
            ->where('status', MentorshipProgramStatus::Active)
            ->exists();

        return $mentorsFreelancer ? Response::allow() : Response::denyAsNotFound();
    }
}
