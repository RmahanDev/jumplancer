<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Models\Skill;
use App\Support\PersianText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * An exam from the exam builder: its skill, settings and questions (any number of them), each with
 * 2 to 8 options of which exactly one — `correct`, an index into `options` — is right.
 */
class SaveExamRequest extends FormRequest
{
    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 8;

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
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'time_limit_minutes' => ['required', 'integer', 'min:1', 'max:300'],
            'total_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_score' => ['required', 'integer', 'min:1', 'lt:total_score'],
            'is_active' => ['boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.body' => ['required', 'string', 'max:2000'],
            'questions.*.hint' => ['nullable', 'string', 'max:1000'],
            'questions.*.options' => ['required', 'array', 'min:'.self::MIN_OPTIONS, 'max:'.self::MAX_OPTIONS],
            'questions.*.options.*.body' => ['required', 'string', 'max:500'],
            'questions.*.correct' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = Category::find($this->integer('category_id'));
                $skill = Skill::find($this->integer('skill_id'));

                if ($category && $skill && ! in_array($skill->category_id, [$category->id, ...$category->children()->pluck('id')->all()], true)) {
                    $validator->errors()->add('skill_id', __('Choose a skill of the selected category.'));
                }

                foreach ((array) $this->input('questions', []) as $index => $question) {
                    $options = (array) ($question['options'] ?? []);
                    $correct = $question['correct'] ?? null;

                    if ($validator->errors()->has("questions.{$index}.correct")) {
                        continue;
                    }

                    if (! is_numeric($correct) || ! array_key_exists((int) $correct, array_values($options))) {
                        $validator->errors()->add("questions.{$index}.correct", __('Mark the correct option of this question.'));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pass_score.lt' => __('The pass mark must be lower than the total score.'),
            'questions.required' => __('Add at least one question.'),
            'questions.min' => __('Add at least one question.'),
            'questions.*.options.min' => __('A question needs at least :min options.', ['min' => PersianText::number(self::MIN_OPTIONS)]),
            'questions.*.correct.required' => __('Mark the correct option of this question.'),
            'questions.*.correct.integer' => __('Mark the correct option of this question.'),
            'questions.*.options.max' => __('A question can have at most :max options.', ['max' => PersianText::number(self::MAX_OPTIONS)]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'questions.*.body' => __('question'),
            'questions.*.options.*.body' => __('option'),
        ];
    }
}
