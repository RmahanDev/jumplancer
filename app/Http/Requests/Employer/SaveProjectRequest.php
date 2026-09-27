<?php

namespace App\Http\Requests\Employer;

use App\Enums\BudgetType;
use App\Models\Category;
use App\Models\CategoryBudgetRange;
use App\Support\PersianText;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * An employer's project. The budget must respect the admin-defined range of its category
 * (v4 rule 4: a sub-category's range overrides its parent's).
 */
class SaveProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $project = $this->route('project');

        return $project === null ? true : Gate::inspect('update', $project);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:8', 'max:200'],
            'description' => ['required', 'string', 'min:30', 'max:10000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNotNull('parent_id')],
            'budget_type' => ['required', Rule::enum(BudgetType::class)],
            'budget_min' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'budget_max' => ['nullable', 'integer', 'gte:budget_min', 'max:10000000000'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'is_beginner_friendly' => ['boolean'],
            'skills' => ['nullable', 'array', 'max:10'],
            'skills.*' => ['integer', 'distinct', Rule::exists('skills', 'id')],
            'submit' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => __('Choose a sub-category for the project.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['category_id', 'budget_type', 'budget_min', 'budget_max'])) {
                    return;
                }

                $range = $this->budgetRange();

                if ($range === null) {
                    return;
                }

                if ($this->integer('budget_min') < $range->min_amount) {
                    $validator->errors()->add('budget_min', __('The minimum budget for this category is :amount Toman.', [
                        'amount' => PersianText::number($range->min_amount),
                    ]));
                }

                $highest = $this->filled('budget_max') ? $this->integer('budget_max') : $this->integer('budget_min');

                if ($range->max_amount !== null && $highest > $range->max_amount) {
                    $validator->errors()->add('budget_max', __('The maximum budget for this category is :amount Toman.', [
                        'amount' => PersianText::number($range->max_amount),
                    ]));
                }
            },
        ];
    }

    /**
     * The active range of the sub-category, or else of its parent.
     */
    private function budgetRange(): ?CategoryBudgetRange
    {
        $category = Category::find($this->integer('category_id'));

        return CategoryBudgetRange::query()
            ->where('budget_type', $this->string('budget_type')->toString())
            ->where('is_active', true)
            ->whereIn('category_id', array_filter([$category?->id, $category?->parent_id]))
            ->get()
            ->sortBy(fn (CategoryBudgetRange $range): int => $range->category_id === $category?->id ? 0 : 1)
            ->first();
    }
}
