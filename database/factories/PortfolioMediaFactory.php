<?php

namespace Database\Factories;

use App\Enums\ModerationStatus;
use App\Enums\PortfolioMediaType;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortfolioMedia>
 */
class PortfolioMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'portfolio_item_id' => PortfolioItem::factory(),
            'file_path' => 'portfolio/'.Str::uuid().'.jpg',
            'file_type' => PortfolioMediaType::Image,
            'original_filename' => 'proof.jpg',
            'status' => ModerationStatus::PendingReview,
        ];
    }

    /**
     * A file a reviewer checked and approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ModerationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
