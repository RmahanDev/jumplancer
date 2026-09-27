<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\EmployerSubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployerSubscription>
 */
class EmployerSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employer_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'projects_used' => 0,
            'status' => SubscriptionStatus::Active,
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }
}
