<?php

namespace App\Http\Resources;

use App\Enums\AdminPermission;
use App\Models\WithdrawalRequest;
use App\Support\BankCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WithdrawalRequest
 */
class WithdrawalRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array. Staff who pay see the full card number; the owner sees it masked.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isStaff = $request->user()?->can(AdminPermission::ManageWithdrawals->value) && $request->routeIs('admin.*');

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'card_number' => $isStaff ? $this->card_number : BankCard::mask($this->card_number),
            'card_masked' => BankCard::mask($this->card_number),
            'holder_name' => $this->holder_name,
            'bank_name' => $this->bank_name,
            'card_phone' => $this->when($isStaff, fn () => $this->bankCard?->phone),
            'tracking_code' => $this->tracking_code,
            'has_receipt' => $this->receipt_path !== null,
            'receipt_url' => $this->receipt_path ? route('withdrawals.receipt', $this->id) : null,
            'rejection_reason' => $this->rejection_reason,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                ...UserResource::summary($this->user),
                'phone' => $this->user->phone,
                'balance' => $this->user->relationLoaded('wallet') ? $this->user->wallet?->balance : null,
                'held_balance' => $this->user->relationLoaded('wallet') ? $this->user->wallet?->held_balance : null,
            ]),
            'processor' => $this->whenLoaded('processor', fn () => UserResource::summary($this->processor)),
        ];
    }
}
