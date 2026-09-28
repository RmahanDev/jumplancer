<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalRequestResource;
use App\Models\WithdrawalRequest;
use App\Services\WalletLedger;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payout requests ("withdrawals.manage", admins and support agents): check the card, transfer the
 * money, then record the bank's tracking number with a receipt image, or reject with a reason.
 */
class WithdrawalController extends Controller
{
    public function __construct(private readonly WalletLedger $ledger) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...array_column(WithdrawalStatus::cases(), 'value'), 'all'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $filters['status'] ?? WithdrawalStatus::Pending->value;
        $search = $filters['search'] ?? null;

        $withdrawals = WithdrawalRequest::query()
            ->with(['user.wallet', 'processor', 'bankCard'])
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($search, function (Builder $query, string $search): void {
                $term = PersianText::normalize($search);
                $digits = PersianText::toLatinDigits($search);

                $query->where(fn (Builder $query) => $query
                    ->whereHas('user', fn (Builder $users) => $users->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$digits}%"))
                    ->orWhere('card_number', 'like', "%{$digits}%")
                    ->orWhere('tracking_code', 'like', "%{$digits}%"));
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [WithdrawalStatus::Pending->value])
            ->when($status === WithdrawalStatus::Pending->value, fn (Builder $query) => $query->oldest()->oldest('id'), fn (Builder $query) => $query->latest()->latest('id'))
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Admin/Withdrawals/Index', [
            'withdrawals' => WithdrawalRequestResource::collection($withdrawals),
            'filters' => ['status' => $status, 'search' => $search],
            'counts' => WithdrawalRequest::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'pendingTotal' => (int) WithdrawalRequest::where('status', WithdrawalStatus::Pending)->sum('amount'),
            'routes' => [
                'pay' => route('admin.withdrawals.pay', ':id'),
                'reject' => route('admin.withdrawals.reject', ':id'),
            ],
        ]);
    }

    /**
     * The transfer is done: a tracking number and a receipt image are both required.
     */
    public function pay(Request $request, WithdrawalRequest $withdrawalRequest): RedirectResponse
    {
        $validated = $request->validate([
            'tracking_code' => ['required', 'string', 'min:4', 'max:100'],
            'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'receipt.required' => __('Attach the image of the payment receipt.'),
        ]);

        $path = $request->file('receipt')->store('withdrawal-receipts', 'local');

        $this->ledger->markWithdrawalPaid(
            $withdrawalRequest,
            $request->user(),
            PersianText::toLatinDigits(trim($validated['tracking_code'])),
            $path,
        );

        $this->toast(__('Marked as paid. :name can see the tracking number and the receipt.', ['name' => $withdrawalRequest->user->name]));

        return back();
    }

    public function reject(Request $request, WithdrawalRequest $withdrawalRequest): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->ledger->cancelWithdrawal($withdrawalRequest, WithdrawalStatus::Rejected, $request->user(), $validated['rejection_reason']);

        $this->toast(__('The request was rejected and the money went back to the wallet.'), 'info');

        return back();
    }
}
