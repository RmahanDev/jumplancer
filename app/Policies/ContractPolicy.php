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

    /**
     * Either side of the contract can ask an expert to step in.
     */
    public function dispute(User $user, Contract $contract): Response
    {
        return in_array($user->id, [$contract->employer_id, $contract->freelancer_id], true)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
