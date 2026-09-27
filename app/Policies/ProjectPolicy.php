<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Employers only see and change their own projects; others get a 404 so ids cannot be probed.
 */
class ProjectPolicy
{
    public function update(User $user, Project $project): Response
    {
        return $project->employer_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, Project $project): Response
    {
        return $this->update($user, $project);
    }

    public function publish(User $user, Project $project): Response
    {
        return $this->update($user, $project);
    }
}
