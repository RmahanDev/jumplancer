<?php

namespace App\Policies;

use App\Models\FreelancerField;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FreelancerFieldPolicy
{
    public function delete(User $user, FreelancerField $freelancerField): Response
    {
        return $freelancerField->freelancer_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
