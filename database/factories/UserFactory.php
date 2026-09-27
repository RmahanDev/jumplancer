<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\EmployerProfile;
use App\Models\FreelancerProfile;
use App\Models\MentorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A user whose account was suspended for sharing contact info.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'Shared contact information in chat.',
        ]);
    }

    /**
     * A platform administrator.
     */
    public function admin(): static
    {
        return $this->withRole(RoleName::Admin);
    }

    /**
     * An employer with an employer profile.
     */
    public function employer(): static
    {
        return $this->withRole(RoleName::Employer)->has(EmployerProfile::factory(), 'employerProfile');
    }

    /**
     * A freelancer with a freelancer profile.
     */
    public function freelancer(): static
    {
        return $this->withRole(RoleName::Freelancer)->has(FreelancerProfile::factory(), 'freelancerProfile');
    }

    /**
     * A mentor with a mentor profile.
     */
    public function mentor(): static
    {
        return $this->withRole(RoleName::Mentor)->has(MentorProfile::factory(), 'mentorProfile');
    }

    /**
     * Assign a role after creating the user, creating the role first when it has not been seeded.
     */
    public function withRole(RoleName $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->assignRole(Role::findOrCreate($role->value, 'web'));
        });
    }
}
