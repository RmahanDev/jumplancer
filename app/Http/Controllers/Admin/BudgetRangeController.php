<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BudgetType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetRangeController extends Controller
{
    /**
     * Set the allowed budget of a category for one budget type (v4 rule: a sub-category row
     * overrides its parent). Projects below the minimum cannot be posted.
     */
    public function __invoke(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'budget_type' => ['required', Rule::enum(BudgetType::class)],
            'min_amount' => ['required', 'integer', 'min:0'],
            'max_amount' => ['nullable', 'integer', 'gte:min_amount'],
            'is_active' => ['boolean'],
        ]);

        $category->budgetRanges()->updateOrCreate(
            ['budget_type' => $validated['budget_type']],
            [
                'min_amount' => $validated['min_amount'],
                'max_amount' => $validated['max_amount'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'updated_by' => $request->user()->id,
            ],
        );

        $this->toast(__('Budget range of ":name" was saved.', ['name' => $category->name]));

        return back();
    }
}
