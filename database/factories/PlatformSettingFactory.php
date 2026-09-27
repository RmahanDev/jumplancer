<?php

namespace Database\Factories;

use App\Enums\SettingValueType;
use App\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformSetting>
 */
class PlatformSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'setting_key' => Str::snake(fake()->unique()->words(3, true)),
            'setting_value' => '1',
            'value_type' => SettingValueType::Int,
            'description' => fake()->sentence(),
        ];
    }
}
