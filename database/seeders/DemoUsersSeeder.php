<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoUsersSeeder extends Seeder
{
    /**
     * Create one demo login per role, for local development only.
     * Credentials are documented in README.md - never use these in production.
     *
     * No per-menu edit overrides are seeded here: User::canEditMenu()
     * already falls back to whatever Manage* permission a role holds when
     * no explicit override exists, so these accounts keep the edit rights
     * their role always had. An Administrator narrows or widens that per
     * user from User Management as needed.
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

        // A single shared, read-only account for the "View as Guest" button
        // on the login page. Not meant to be logged into directly - the
        // password is random and never surfaced anywhere.
        User::firstOrCreate(
            ['email' => 'guest@mpd.test'],
            [
                'name' => 'Guest Viewer',
                'password' => Str::random(40),
                'email_verified_at' => now(),
            ]
        )->syncRoles([RoleName::Guest->value]);
    }
}
