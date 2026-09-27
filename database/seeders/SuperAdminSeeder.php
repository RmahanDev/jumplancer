<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create the root super admin from config/jumplancer.php (values come from .env).
     *
     * An existing account is only re-assigned the role: its password is never
     * reset, so changing it from the dashboard survives a re-seed.
     */
    public function run(): void
    {
        $config = config('jumplancer.super_admin');

        if (blank($config['password'] ?? null)) {
            $this->command?->warn('SUPER_ADMIN_PASSWORD is empty in .env - the root super admin was not created.');

            return;
        }

        $superAdmin = User::withTrashed()->firstOrCreate(
            ['username' => $config['username']],
            [
                'name' => $config['name'],
                'email' => $config['email'],
                'password' => $config['password'],
            ],
        );

        if ($superAdmin->trashed()) {
            $superAdmin->restore();
        }

        $superAdmin->forceFill(['email_verified_at' => $superAdmin->email_verified_at ?? now()])->save();
        $superAdmin->assignRole(Role::findOrCreate(RoleName::SuperAdmin->value, 'web'));
        $superAdmin->ensureRoleProfiles();
    }
}
