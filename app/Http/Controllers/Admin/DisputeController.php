<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    /**
     * Move a dispute through review, or close it with a written decision.
     */
    public function __invoke(Request $request, Dispute $dispute): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(DisputeStatus::class), Rule::notIn([DisputeStatus::Open->value])],
            'resolution_note' => ['nullable', 'required_unless:status,'.DisputeStatus::UnderReview->value, 'string', 'max:2000'],
        ]);

        $status = DisputeStatus::from($validated['status']);
        $isFinal = in_array($status, [DisputeStatus::Resolved, DisputeStatus::Rejected], true);

        DB::transaction(function () use ($dispute, $status, $isFinal, $validated, $request): void {
            $dispute->update([
                'status' => $status,
                'resolution_note' => $validated['resolution_note'] ?? $dispute->resolution_note,
                'resolved_by' => $isFinal ? $request->user()->id : null,
                'resolved_at' => $isFinal ? now() : null,
            ]);

            // A disputed contract goes back to work once the dispute is closed.
            if ($isFinal && $dispute->contract->status === ContractStatus::Disputed) {
                $dispute->contract->update(['status' => ContractStatus::Active]);
            }
        });

        $this->toast($isFinal ? __('The dispute was closed.') : __('The dispute is under review.'));

        return back();
    }
}
