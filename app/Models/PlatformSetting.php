<?php

namespace App\Models;

use App\Enums\SettingValueType;
use Database\Factories\PlatformSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-editable business rule (free-project window, free mentorships, exam fees ...).
 */
#[Fillable(['setting_key', 'setting_value', 'value_type', 'description', 'updated_by'])]
class PlatformSetting extends Model
{
    /** @use HasFactory<PlatformSettingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_type' => SettingValueType::class,
        ];
    }

    /**
     * Admin who last changed the setting.
     *
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The setting value cast according to value_type.
     *
     * @return Attribute<int|bool|string|null, never>
     */
    protected function typedValue(): Attribute
    {
        return Attribute::get(fn (): int|bool|string|null => match (true) {
            $this->setting_value === null => null,
            $this->value_type === SettingValueType::Int, $this->value_type === SettingValueType::Money => (int) $this->setting_value,
            $this->value_type === SettingValueType::Bool => filter_var($this->setting_value, FILTER_VALIDATE_BOOLEAN),
            default => $this->setting_value,
        });
    }
}
