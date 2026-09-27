<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContractPolicy
{
    /**
     * The employer manages milestones, completion and the review of their contract.
     */
    public function manage(User $user, Contract $contract): Response
    {
        return $contract->employer_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
