<?php

namespace Database\Factories;

use App\Enums\BudgetType;
use App\Models\Category;
use App\Models\CategoryBudgetRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryBudgetRange>
 */
class CategoryBudgetRangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'budget_type' => BudgetType::Fixed,
            'min_amount' => 1_000_000,
            'max_amount' => 50_000_000,
            'is_active' => true,
        ];
    }
}
