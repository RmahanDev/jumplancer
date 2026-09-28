<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use App\Http\Requests\Concerns\NormalizesAccountFields;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create or edit a marketplace member (freelancer, employer, mentor) from the admin panel.
 * Access is guarded by the "users.manage" permission on the route and UserPolicy.
 */
class SaveMemberRequest extends FormRequest
{
    use NormalizesAccountFields;

    /**
     * Roles an admin can give from the users page. Staff roles are handled on the admins page.
     *
     * @return list<string>
     */
    public static function memberRoles(): array
    {
        return [RoleName::Freelancer->value, RoleName::Employer->value, RoleName::Mentor->value];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $member = $this->route('user');

        return $member === null ? true : Gate::inspect('update', $member);
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
        $member = $this->route('user');

        return [
            ...$this->accountRules($member),
            'password' => [$member ? 'nullable' : 'required', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::in(self::memberRoles())],
        ];
    }
}
