<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContractStatus;
use App\Enums\DisputeOutcome;
use App\Enums\DisputeStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Services\WalletLedger;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    /**
     * Move a dispute through review, or close it with the expert's written decision. Resolving it
     * also decides the money held for the contract (the good-faith deposit and funded milestones):
     * keep working, refund everything to the employer, or pay everything to the freelancer.
     */
    public function __invoke(Request $request, Dispute $dispute, WalletLedger $ledger): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(DisputeStatus::class), Rule::notIn([DisputeStatus::Open->value])],
            'outcome' => ['nullable', 'required_if:status,'.DisputeStatus::Resolved->value, Rule::enum(DisputeOutcome::class)],
            'resolution_note' => ['nullable', 'required_unless:status,'.DisputeStatus::UnderReview->value, 'string', 'max:2000'],
        ], [
            'outcome.required_if' => __('Choose what happens to the money held for this contract.'),
        ]);

        if (in_array($dispute->status, [DisputeStatus::Resolved, DisputeStatus::Rejected], true)) {
            throw ValidationException::withMessages(['status' => __('This dispute is already closed.')]);
        }

        $status = DisputeStatus::from($validated['status']);
        $isFinal = in_array($status, [DisputeStatus::Resolved, DisputeStatus::Rejected], true);
        $outcome = $status === DisputeStatus::Resolved ? DisputeOutcome::from($validated['outcome']) : null;

        $moved = DB::transaction(function () use ($dispute, $status, $isFinal, $outcome, $validated, $request, $ledger): int {
            $dispute->update([
                'status' => $status,
                'outcome' => $outcome,
                'resolution_note' => $validated['resolution_note'] ?? $dispute->resolution_note,
                'resolved_by' => $isFinal ? $request->user()->id : null,
                'resolved_at' => $isFinal ? now() : null,
            ]);

            $contract = $dispute->contract;

            if (! $isFinal || $contract->status !== ContractStatus::Disputed) {
                return 0;
            }

            $moved = $outcome ? $ledger->settleDispute($contract, $outcome) : 0;

            // Refund ends the contract; paying the freelancer means the work was accepted; otherwise work goes on.
            [$contractStatus, $projectStatus] = match ($outcome) {
                DisputeOutcome::RefundEmployer => [ContractStatus::Cancelled, ProjectStatus::Cancelled],
                DisputeOutcome::PayFreelancer => [ContractStatus::Completed, ProjectStatus::Completed],
                default => [ContractStatus::Active, null],
            };

            $contract->update([
                'status' => $contractStatus,
                'completed_at' => $contractStatus === ContractStatus::Completed ? now() : $contract->completed_at,
            ]);

            if ($projectStatus !== null) {
                $contract->project->update(['status' => $projectStatus]);
            }

            return $moved;
        });

        $this->toast(match (true) {
            ! $isFinal => __('The dispute is under review.'),
            $outcome === DisputeOutcome::RefundEmployer => __('The dispute was closed and :amount Toman went back to the employer.', ['amount' => PersianText::number($moved)]),
            $outcome === DisputeOutcome::PayFreelancer => __('The dispute was closed and :amount Toman was paid to the freelancer.', ['amount' => PersianText::number($moved)]),
            default => __('The dispute was closed.'),
        });

        return back();
    }
}
