<?php

namespace App\Http\Requests\Admin;

use App\Enums\BudgetType;
use App\Enums\ProjectStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff edit of any project ("projects.manage").
 */
class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNotNull('parent_id')],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'budget_type' => ['required', Rule::enum(BudgetType::class)],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0', 'gte:budget_min'],
            'is_beginner_friendly' => ['boolean'],
            'deadline' => ['nullable', 'date'],
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
}
