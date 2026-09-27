<?php

namespace App\Http\Controllers;

use App\Services\WalletLedger;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalletDepositController extends Controller
{
    /**
     * Top up the wallet through the sandbox gateway (disabled in production).
     */
    public function __invoke(Request $request, WalletLedger $ledger): RedirectResponse
    {
        abort_unless(config('jumplancer.payments.sandbox'), 404);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:10000', 'max:'.config('jumplancer.payments.max_sandbox_deposit')],
        ]);

        $ledger->deposit($request->user(), $validated['amount']);

        $this->toast(__('Your wallet was charged with :amount Toman.', ['amount' => PersianText::number($validated['amount'])]));

        return back();
    }
}
