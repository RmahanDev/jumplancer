<?php

namespace Database\Factories;

use App\Enums\CompanySize;
use App\Models\EmployerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployerProfile>
 */
class EmployerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'company_size' => fake()->randomElement(CompanySize::cases()),
            'industry' => fake()->word(),
            'website' => fake()->url(),
            'open_to_beginners' => true,
            'free_projects_used' => 0,
        ];
    }
}
