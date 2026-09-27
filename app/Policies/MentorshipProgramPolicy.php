<?php

namespace App\Policies;

use App\Models\MentorshipProgram;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MentorshipProgramPolicy
{
    /**
     * Only the program's mentor changes it or schedules its sessions.
     */
    public function update(User $user, MentorshipProgram $mentorshipProgram): Response
    {
        return $mentorshipProgram->mentor_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
