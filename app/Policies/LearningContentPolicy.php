<?php

namespace App\Policies;

use App\Models\LearningContent;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Mentors manage the content they wrote. Staff edit any content from the admin panel
 * behind the "content.manage" permission, which does not go through this policy.
 */
class LearningContentPolicy
{
    public function update(User $user, LearningContent $learningContent): Response
    {
        return $learningContent->author_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, LearningContent $learningContent): Response
    {
        return $this->update($user, $learningContent);
    }
}
