<?php

namespace App\Http\Requests\Freelancer;

use App\Support\PersianText;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A structured case study (v3 anti-disintermediation design: no raw links, files are reviewed).
 */
class SavePortfolioItemRequest extends FormRequest
{
    /**
     * Files a case study may hold.
     */
    public const MAX_FILES = 5;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $item = $this->route('portfolioItem');

        return $item === null ? true : Gate::inspect('update', $item);
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
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'role' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'outcome' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_visible' => ['boolean'],
            'skills' => ['nullable', 'array', 'max:10'],
            'skills.*' => ['integer', 'distinct', Rule::exists('skills', 'id')],
            'files' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => ['integer'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $item = $this->route('portfolioItem');
                $kept = $item ? $item->media()->whereNotIn('id', $this->input('remove_media', []))->count() : 0;

                if ($kept + count($this->file('files', [])) > self::MAX_FILES) {
                    $validator->errors()->add('files', __('A case study can have at most :count files.', ['count' => PersianText::number(self::MAX_FILES)]));
                }
            },
        ];
    }
}
