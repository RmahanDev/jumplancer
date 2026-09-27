<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
            BadgeSeeder::class,
            PlatformSettingSeeder::class,
        ]);

        if (app()->isLocal() && User::doesntExist()) {
            $this->seedDemoUsers();
        }
    }

    /**
     * One account per role for local development. Every password is "password".
     */
    private function seedDemoUsers(): void
    {
        User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@jumplancer.test']);
        User::factory()->employer()->create(['name' => 'Demo Employer', 'email' => 'employer@jumplancer.test']);
        User::factory()->freelancer()->create(['name' => 'Demo Freelancer', 'email' => 'freelancer@jumplancer.test']);
        User::factory()->mentor()->create(['name' => 'Demo Mentor', 'email' => 'mentor@jumplancer.test']);
    }
}
