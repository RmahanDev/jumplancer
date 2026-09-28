<?php

namespace App\Http\Controllers\Freelancer;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    /**
     * The freelancer's contracts with milestones to deliver.
     */
    public function __invoke(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(ContractStatus::class)]])['status'] ?? null;

        $contracts = $request->user()->freelancerContracts()
            ->with(['project', 'employer', 'mentor', 'openDispute', 'mentorshipTicket', 'milestones' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ContractStatus::Active->value])
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Freelancer/Contracts/Index', [
            'contracts' => ContractResource::collection($contracts),
            'filters' => ['status' => $status],
            'routes' => [
                'submit' => route('freelancer.milestones.submit', ':id'),
                'dispute' => route('contracts.disputes.store', ':id'),
            ],
        ]);
    }
}
