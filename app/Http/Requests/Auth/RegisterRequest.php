<?php

namespace App\Http\Requests\Auth;

use App\Enums\RoleName;
use App\Http\Requests\Concerns\NormalizesAccountFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesAccountFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAccountFields();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->accountRules(),
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(array_column(RoleName::selfRegistrable(), 'value'))],
        ];
    }
}
