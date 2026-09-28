<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The employer or the freelancer asks an expert to decide (money held in escrow stays put until then).
 */
class ContractDisputeController extends Controller
{
    public function __invoke(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('dispute', $contract);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        if ($contract->status !== ContractStatus::Active) {
            throw ValidationException::withMessages(['reason' => __('Only active contracts can be disputed.')]);
        }

        DB::transaction(function () use ($contract, $validated, $request): void {
            $contract->disputes()->create([
                'raised_by' => $request->user()->id,
                'reason' => $validated['reason'],
                'status' => DisputeStatus::Open,
            ]);

            $contract->update(['status' => ContractStatus::Disputed]);
        });

        $this->toast(__('Your request reached our experts. The money held for this contract stays in escrow until they decide.'), 'info');

        return back();
    }
}
