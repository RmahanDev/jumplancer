<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminPermission;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Requests\Concerns\NormalizesAccountFields;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create or edit an admin, support agent or super admin. Nobody can hand out a permission they do not hold,
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
        // Older clients sent a boolean; the role select is the source of truth now.
        if (! $this->filled('role')) {
            $this->merge(['role' => $this->boolean('is_super_admin') ? RoleName::SuperAdmin->value : RoleName::Admin->value]);
        }
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
            'role' => ['required', Rule::in(RoleName::staffValues()), Rule::when(! $actor->isSuperAdmin(), [Rule::notIn([RoleName::SuperAdmin->value])])],
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
            'role.not_in' => __('Only a super admin can create another super admin.'),
            'permissions.*.in' => __('You can only grant permissions that you have yourself.'),
        ];
    }

    public function role(): RoleName
    {
        return RoleName::from($this->validated('role'));
    }

    /**
     * Permissions to store: super admins hold everything implicitly, so none are stored for them.
     *
     * @return list<string>
     */
    public function grantedPermissions(): array
    {
        return $this->role() === RoleName::SuperAdmin
            ? []
            : array_values(array_intersect(AdminPermission::values(), $this->validated('permissions')));
    }
}
