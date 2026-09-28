<?php

namespace App\Http\Controllers\Employer;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Rate the freelancer once the contract is complete (one review per contract).
     */
    public function __invoke(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('manage', $contract);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($contract->status !== ContractStatus::Completed) {
            throw ValidationException::withMessages(['rating' => __('You can review the freelancer after the contract is complete.')]);
        }

        if ($contract->reviews()->where('reviewer_id', $request->user()->id)->exists()) {
            throw ValidationException::withMessages(['rating' => __('You already reviewed this contract.')]);
        }

        $contract->reviews()->create([
            'reviewer_id' => $request->user()->id,
            'reviewee_id' => $contract->freelancer_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        $this->toast(__('Thank you! Your review helps beginners grow.'));

        return back();
    }
}
