<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'bio' => $this->bio,
            'avatar' => self::avatarUrl($this->resource),
            'status' => $this->status->value,
            'suspension_reason' => $this->suspension_reason,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'freelancer_level' => $this->whenLoaded('freelancerProfile', fn () => $this->freelancerProfile?->level->value),
            'company_name' => $this->whenLoaded('employerProfile', fn () => $this->employerProfile?->company_name),
            'mentor_verified' => $this->whenLoaded('mentorProfile', fn () => $this->mentorProfile?->is_verified),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * The minimal shape used when a user appears inside another record (project owner, mentor ...).
     *
     * @return array{id: int, name: string, username: ?string, avatar: ?string}|null
     */
    public static function summary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => self::avatarUrl($user),
        ];
    }

    private static function avatarUrl(User $user): ?string
    {
        return $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null;
    }
}
