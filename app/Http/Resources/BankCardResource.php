<?php

namespace App\Http\Resources;

use App\Models\BankCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankCard
 */
class BankCardResource extends JsonResource
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
            'card_masked' => $this->maskedNumber(),
            'last4' => substr($this->card_number, -4),
            'holder_name' => $this->holder_name,
            'phone' => $this->phone,
            'bank_name' => $this->bank_name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
