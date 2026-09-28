<?php

namespace App\Policies;

use App\Models\PortfolioItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PortfolioItemPolicy
{
    public function update(User $user, PortfolioItem $portfolioItem): Response
    {
        return $portfolioItem->freelancer_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, PortfolioItem $portfolioItem): Response
    {
        return $this->update($user, $portfolioItem);
    }
}
