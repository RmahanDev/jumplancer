<?php

namespace App\Http\Resources;

use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlatformSetting
 */
class PlatformSettingResource extends JsonResource
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
            'setting_key' => $this->setting_key,
            'setting_value' => $this->setting_value,
            'typed_value' => $this->typed_value,
            'value_type' => $this->value_type->value,
            'description' => $this->description,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'editor' => $this->whenLoaded('editor', fn () => UserResource::summary($this->editor)),
        ];
    }
}
