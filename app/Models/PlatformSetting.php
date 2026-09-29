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
     * Typed value of a setting, or the default when the row is missing or empty.
     */
    public static function valueOf(string $key, int|float|bool|string|null $default = null): int|float|bool|string|null
    {
        return static::query()->firstWhere('setting_key', $key)?->typed_value ?? $default;
    }

    /**
     * Share of the proposal price held as a good-faith deposit when an employer hires.
     */
    public static function hireDepositPercent(): int
    {
        return max(0, min(100, (int) static::valueOf('hire_deposit_percent', 45)));
    }

    /**
     * Fees taken from the freelancer's payments, in percent of each amount paid out:
     * platform = base fee, mentorship = extra fee when the freelancer has a mentor,
     * mentor_share = the part of the paid amount that goes to the mentor (out of the fees).
     *
     * @return array{platform: float, mentorship: float, mentor_share: float}
     */
    public static function fees(): array
    {
        return [
            'platform' => (float) static::valueOf('platform_fee_percent', 20),
            'mentorship' => (float) static::valueOf('mentorship_fee_percent', 5),
            'mentor_share' => (float) static::valueOf('mentor_share_percent', 3.5),
        ];
    }

    /**
     * The setting value cast according to value_type.
     *
     * @return Attribute<int|float|bool|string|null, never>
     */
    protected function typedValue(): Attribute
    {
        return Attribute::get(fn (): int|float|bool|string|null => match (true) {
            $this->setting_value === null => null,
            $this->value_type === SettingValueType::Percent => (float) $this->setting_value,
            $this->value_type === SettingValueType::Int, $this->value_type === SettingValueType::Money => (int) $this->setting_value,
            $this->value_type === SettingValueType::Bool => filter_var($this->setting_value, FILTER_VALIDATE_BOOLEAN),
            default => $this->setting_value,
        });
    }
}
