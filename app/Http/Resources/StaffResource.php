<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * An admin or super admin as shown on the admins page and the permission matrix.
 *
 * @mixin User
 */
class StaffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $management = Gate::forUser($request->user())->inspect('manageStaff', $this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'is_super_admin' => $this->isSuperAdmin(),
            'is_root' => $this->isRootSuperAdmin(),
            'is_you' => $this->resource->is($request->user()),
            'permissions' => $this->staffPermissions(),
            'can_manage' => $management->allowed(),
            'locked_reason' => $management->denied() ? $management->message() : null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
