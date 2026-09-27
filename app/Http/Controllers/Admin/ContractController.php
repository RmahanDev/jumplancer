<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use App\Http\Resources\DisputeResource;
use App\Models\Contract;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contracts with their milestones, and the disputes raised on them (two tabs).
 */
class ContractController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['contracts', 'disputes'])],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $tab = $filters['tab'] ?? 'contracts';
        $status = $filters['status'] ?? null;

        return Inertia::render('Admin/Contracts/Index', [
            'tab' => $tab,
            'filters' => ['status' => $status],
            'contracts' => $tab === 'contracts' ? ContractResource::collection(
                Contract::query()
                    ->with(['project', 'employer', 'freelancer', 'mentor', 'milestones'])
                    ->withCount(['disputes' => fn ($query) => $query->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])])
                    ->when(ContractStatus::tryFrom((string) $status), fn ($query, ContractStatus $status) => $query->where('status', $status))
                    ->latest()
                    ->latest('id')
                    ->paginate(config('jumplancer.per_page'))
                    ->withQueryString(),
            ) : null,
            'disputes' => $tab === 'disputes' ? DisputeResource::collection(
                Dispute::query()
                    ->with(['contract.project', 'contract.employer', 'contract.freelancer', 'initiator', 'resolver'])
                    ->when(DisputeStatus::tryFrom((string) $status), fn ($query, DisputeStatus $status) => $query->where('status', $status))
                    ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [DisputeStatus::Open->value, DisputeStatus::UnderReview->value])
                    ->latest()
                    ->latest('id')
                    ->paginate(config('jumplancer.per_page'))
                    ->withQueryString(),
            ) : null,
            'counts' => [
                'contracts' => Contract::count(),
                'active_contracts' => Contract::where('status', ContractStatus::Active)->count(),
                'open_disputes' => Dispute::whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->count(),
            ],
            'options' => [
                'contractStatuses' => array_column(ContractStatus::cases(), 'value'),
                'disputeStatuses' => array_column(DisputeStatus::cases(), 'value'),
            ],
            'routes' => [
                'disputeUpdate' => route('admin.disputes.update', ':id'),
            ],
        ]);
    }
}
