<?php

namespace App\Http\Resources;

use App\Models\PortfolioMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PortfolioMedia
 */
class PortfolioMediaResource extends JsonResource
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
            'file_type' => $this->file_type->value,
            'original_filename' => $this->original_filename,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'url' => route('portfolio-media.show', $this->resource),
            'item' => $this->whenLoaded('portfolioItem', fn () => [
                'id' => $this->portfolioItem->id,
                'title' => $this->portfolioItem->title,
                'freelancer' => $this->portfolioItem->relationLoaded('freelancer') ? UserResource::summary($this->portfolioItem->freelancer) : null,
            ]),
        ];
    }
}
