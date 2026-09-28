<?php

namespace App\Http\Controllers;

use App\Enums\WithdrawalStatus;
use App\Http\Requests\Wallet\StoreBankCardRequest;
use App\Models\BankCard;
use App\Support\BankCard as CardNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Payout cards of the signed-in user (wallet page).
 */
class BankCardController extends Controller
{
    public function store(StoreBankCardRequest $request): RedirectResponse
    {
        $number = $request->validated('card_number');

        $request->user()->bankCards()->create([
            'card_number' => $number,
            'holder_name' => $request->user()->name,
            'phone' => $request->validated('phone'),
            'bank_name' => CardNumber::bankName($number),
        ]);

        $this->toast(__('Your card was registered. Payouts are sent to it.'));

        return back();
    }

    public function destroy(Request $request, BankCard $bankCard): RedirectResponse
    {
        abort_unless($bankCard->user_id === $request->user()->id, 404);

        if ($bankCard->withdrawalRequests()->where('status', WithdrawalStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['card' => __('A withdrawal to this card is still being processed.')]);
        }

        $bankCard->delete();

        $this->toast(__('The card was removed.'), 'info');

        return back();
    }
}
