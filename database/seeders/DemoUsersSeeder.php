<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    /**
     * Create one demo login per role, for local development only.
     * Credentials are documented in README.md - never use these in production.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Ade Admin', 'email' => 'admin@mpd.test', 'role' => RoleName::Administrator],
            ['name' => 'Made Manager', 'email' => 'manager@mpd.test', 'role' => RoleName::MaintenanceManager],
            ['name' => 'Susi Supervisor', 'email' => 'supervisor@mpd.test', 'role' => RoleName::MaintenanceSupervisor],
            ['name' => 'Toni Technician', 'email' => 'staff@mpd.test', 'role' => RoleName::MaintenanceStaff],
            ['name' => 'Hesti HR', 'email' => 'hr@mpd.test', 'role' => RoleName::PeopleDevelopment],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$account['role']->value]);
        }
    }
}
