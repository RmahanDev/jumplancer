<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Resources\BankCardResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WithdrawalRequestResource;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    /**
     * Ledger types a user can have. Mentoring and exams stay out of employers' filters: employers
     * never see that the freelancers they hire work with a mentor.
     *
     * @return list<TransactionType>
     */
    private function typesFor(User $user): array
    {
        $hidden = $user->hasAnyRole([RoleName::Freelancer->value, RoleName::Mentor->value])
            ? []
            : [TransactionType::MentorPayout, TransactionType::MentorshipFee, TransactionType::ExamFee];

        return array_values(array_filter(TransactionType::cases(), fn (TransactionType $type): bool => ! in_array($type, $hidden, true)));
    }

    /**
     * Balance, escrow, payout cards, withdrawal requests and the ledger of the signed-in user's wallet.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(TransactionType::class)],
        ]);

        $user = $request->user();
        $wallet = $user->ensureWallet();

        $transactions = $wallet->transactions()
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        $succeeded = $wallet->transactions()->where('status', TransactionStatus::Succeeded);

        return Inertia::render('Shared/Wallet', [
            'wallet' => [
                'balance' => $wallet->balance,
                'held_balance' => $wallet->held_balance,
            ],
            'totals' => [
                'income' => (int) (clone $succeeded)->where('amount', '>', 0)->sum('amount'),
                'spent' => (int) abs((clone $succeeded)->where('amount', '<', 0)->sum('amount')),
            ],
            'transactions' => TransactionResource::collection($transactions),
            'filters' => ['type' => $filters['type'] ?? null],
            'types' => array_column($this->typesFor($user), 'value'),
            'sandbox' => [
                'enabled' => (bool) config('jumplancer.payments.sandbox'),
                'max' => config('jumplancer.payments.max_sandbox_deposit'),
            ],
            'cards' => BankCardResource::collection($user->bankCards()->latest('id')->get()),
            'withdrawals' => WithdrawalRequestResource::collection($user->withdrawalRequests()->latest()->latest('id')->limit(10)->get()),
            'withdrawal' => [
                'minimum' => (int) PlatformSetting::valueOf('withdrawal_min_amount', 50_000),
                'pending' => (int) $user->withdrawalRequests()->where('status', WithdrawalStatus::Pending)->sum('amount'),
                'account_name' => $user->name,
                'account_phone' => $user->phone,
            ],
            'routes' => [
                'deposit' => route('wallet.deposits.store'),
                'cardStore' => route('wallet.cards.store'),
                'cardDestroy' => route('wallet.cards.destroy', ':id'),
                'withdrawalStore' => route('wallet.withdrawals.store'),
                'withdrawalDestroy' => route('wallet.withdrawals.destroy', ':id'),
                'profile' => route('profile.edit'),
            ],
        ]);
    }
}
