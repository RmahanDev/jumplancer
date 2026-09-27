<?php

namespace Tests\Feature\Models;

use App\Enums\SettingValueType;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlatformSettingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{SettingValueType, string|null, int|bool|string|null}>
     */
    public static function values(): array
    {
        return [
            'int' => [SettingValueType::Int, '30', 30],
            'money' => [SettingValueType::Money, '250000', 250000],
            'bool true' => [SettingValueType::Bool, '1', true],
            'bool false' => [SettingValueType::Bool, '0', false],
            'text' => [SettingValueType::Text, 'intermediate', 'intermediate'],
            'unset money' => [SettingValueType::Money, null, null],
        ];
    }

    #[DataProvider('values')]
    public function test_typed_value_casts_the_stored_text_by_value_type(SettingValueType $type, ?string $stored, int|bool|string|null $expected): void
    {
        $setting = PlatformSetting::factory()->create(['value_type' => $type, 'setting_value' => $stored]);

        $this->assertSame($expected, $setting->typed_value);
    }
}
