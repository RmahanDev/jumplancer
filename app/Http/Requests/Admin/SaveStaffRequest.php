<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminPermission;
use App\Enums\UserStatus;
use App\Http\Requests\Concerns\NormalizesAccountFields;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create or edit an admin / super admin. Nobody can hand out a permission they do not hold,
 * and only super admins can create or promote other super admins.
 */
class SaveStaffRequest extends FormRequest
{
    use NormalizesAccountFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $staff = $this->route('staff');

        if ($staff !== null && ! $staff->isStaff()) {
            return Response::denyAsNotFound();
        }

        return $staff === null ? true : Gate::inspect('manageStaff', $staff);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAccountFields();
        $this->merge(['is_super_admin' => $this->boolean('is_super_admin')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $staff = $this->route('staff');
        $actor = $this->user();

        return [
            ...$this->accountRules($staff),
            'password' => [$staff ? 'nullable' : 'required', Password::defaults()],
            'is_super_admin' => ['boolean', Rule::when(! $actor->isSuperAdmin(), ['declined'])],
            'status' => [$staff ? 'required' : 'nullable', Rule::in([UserStatus::Active->value, UserStatus::Suspended->value])],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in($actor->staffPermissions())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_super_admin.declined' => __('Only a super admin can create another super admin.'),
            'permissions.*.in' => __('You can only grant permissions that you have yourself.'),
        ];
    }

    /**
     * Permissions to store: super admins hold everything implicitly, so none are stored for them.
     *
     * @return list<string>
     */
    public function grantedPermissions(): array
    {
        return $this->boolean('is_super_admin')
            ? []
            : array_values(array_intersect(AdminPermission::values(), $this->validated('permissions')));
    }
}
