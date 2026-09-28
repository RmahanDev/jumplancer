<?php

namespace App\Http\Controllers\Employer;

use App\Enums\ContractStatus;
use App\Enums\MilestoneStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Milestone;
use App\Services\WalletLedger;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Escrow steps of a contract: plan, fund from the wallet, then approve and release.
 */
class MilestoneController extends Controller
{
    public function store(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('manage', $contract);

        if ($contract->status !== ContractStatus::Active) {
            throw ValidationException::withMessages(['title' => __('Milestones can only be added to an active contract.')]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'integer', 'min:1000'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $planned = (int) $contract->milestones()->where('status', '!=', MilestoneStatus::Refunded)->sum('amount');

        if ($planned + $validated['amount'] > $contract->amount) {
            throw ValidationException::withMessages(['amount' => __('Milestones cannot exceed the contract amount. :amount Toman is left to plan.', [
                'amount' => PersianText::number(max(0, $contract->amount - $planned)),
            ])]);
        }

        $contract->milestones()->create([
            ...$validated,
            'status' => MilestoneStatus::Pending,
            'sort_order' => $contract->milestones()->count() + 1,
        ]);

        $this->toast(__('Milestone ":title" was added. Fund it so the freelancer can start.', ['title' => $validated['title']]));

        return back();
    }

    /**
     * "fund" locks the amount in escrow; "release" pays a delivered milestone to the freelancer.
     */
    public function update(Request $request, Milestone $milestone, WalletLedger $ledger): RedirectResponse
    {
        Gate::authorize('manage', $milestone);

        $action = $request->validate(['action' => ['required', Rule::in(['fund', 'release'])]])['action'];

        if ($action === 'fund') {
            $ledger->fundMilestone($milestone);
            $this->toast(__('Milestone ":title" was funded and is held in escrow.', ['title' => $milestone->title]));
        } else {
            $ledger->releaseMilestone($milestone);
            $this->toast(__('Payment for ":title" was released to the freelancer.', ['title' => $milestone->title]));
        }

        return back();
    }
}
