<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'gateway' => $this->gateway,
            'gateway_ref' => $this->gateway_ref,
            'description' => $this->description,
            'contract_id' => $this->contract_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'owner' => $this->whenLoaded('wallet', fn () => $this->wallet->relationLoaded('user') ? UserResource::summary($this->wallet->user) : null),
        ];
    }
}
