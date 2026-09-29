<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform ledger (read only: rows are never edited).
 */
class TransactionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'status' => ['nullable', Rule::enum(TransactionStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $transactions = Transaction::query()
            ->with('wallet.user')
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.PersianText::normalize($search).'%';
                $query->where(fn (Builder $query) => $query
                    ->where('gateway_ref', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('wallet.user', fn (Builder $users) => $users->where('name', 'like', $term)->orWhere('username', 'like', $term)));
            })
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        $succeeded = fn (TransactionType ...$types): int => (int) abs(Transaction::whereIn('type', $types)->where('status', TransactionStatus::Succeeded)->sum('amount'));

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => TransactionResource::collection($transactions),
            'filters' => [
                'type' => $filters['type'] ?? null,
                'status' => $filters['status'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
            'summary' => [
                'deposits' => $succeeded(TransactionType::Deposit),
                // Platform fee income after the mentors' share (paid out of the fees).
                'fees' => $succeeded(TransactionType::Fee) - $succeeded(TransactionType::MentorPayout),
                'plans' => $succeeded(TransactionType::PlanPurchase),
                'released' => $succeeded(TransactionType::EscrowRelease),
                'escrow' => (int) Wallet::sum('held_balance'),
            ],
            'options' => [
                'types' => array_column(TransactionType::cases(), 'value'),
                'statuses' => array_column(TransactionStatus::cases(), 'value'),
            ],
        ]);
    }
}
