<?php

namespace App\Http\Controllers;

use App\Enums\WithdrawalStatus;
use App\Models\PlatformSetting;
use App\Models\WithdrawalRequest;
use App\Services\WalletLedger;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Pay my wallet money to my card" requests of the signed-in user.
 */
class WithdrawalController extends Controller
{
    public function __construct(private readonly WalletLedger $ledger) {}

    /**
     * Ask for any amount of the available balance, or all of it.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $balance = $user->ensureWallet()->balance;
        $minimum = min((int) PlatformSetting::valueOf('withdrawal_min_amount', 50_000), max($balance, 1));

        $validated = $request->validate([
            'bank_card_id' => ['required', 'integer', Rule::exists('bank_cards', 'id')->where('user_id', $user->id)],
            'withdraw_all' => ['boolean'],
            'amount' => ['exclude_if:withdraw_all,true', 'required', 'integer', 'min:'.$minimum],
        ], [
            'bank_card_id.required' => __('Choose the card to receive the money.'),
            'amount.min' => __('The smallest withdrawal is :amount Toman.', ['amount' => PersianText::number($minimum)]),
        ]);

        $amount = $request->boolean('withdraw_all') ? $balance : (int) $validated['amount'];

        if ($amount <= 0) {
            return back()->withErrors(['amount' => __('There is no withdrawable balance in your wallet.')]);
        }

        $card = $user->bankCards()->findOrFail($validated['bank_card_id']);
        $this->ledger->requestWithdrawal($user, $card, $amount);

        $this->toast(__('Your request to withdraw :amount Toman was registered. Our team pays it to your card and attaches the receipt.', [
            'amount' => PersianText::number($amount),
        ]));

        return back();
    }

    /**
     * Cancel a request that nobody has processed yet; the money returns to the wallet.
     */
    public function destroy(Request $request, WithdrawalRequest $withdrawalRequest): RedirectResponse
    {
        abort_unless($withdrawalRequest->user_id === $request->user()->id, 404);

        $this->ledger->cancelWithdrawal($withdrawalRequest, WithdrawalStatus::Cancelled);

        $this->toast(__('The withdrawal request was cancelled and the money is back in your wallet.'), 'info');

        return back();
    }
}
