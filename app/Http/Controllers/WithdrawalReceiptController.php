<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermission;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WithdrawalReceiptController extends Controller
{
    /**
     * The payment receipt image, from private storage: for the owner and for staff who handle payouts.
     */
    public function __invoke(Request $request, WithdrawalRequest $withdrawalRequest): StreamedResponse
    {
        $user = $request->user();

        abort_unless($withdrawalRequest->user_id === $user->id || $user->can(AdminPermission::ManageWithdrawals->value), 404);
        abort_unless($withdrawalRequest->receipt_path && Storage::disk('local')->exists($withdrawalRequest->receipt_path), 404);

        return Storage::disk('local')->response($withdrawalRequest->receipt_path, null, [], 'inline');
    }
}
