<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed roles and permissions, and attach the baseline permission set to
     * each role (RoleName::defaultPermissions() - the same source the Roles
     * & Permissions screen's "Reset to default" action reads from).
     * Idempotent: safe to re-run without creating duplicates.
     */
    public function run(): void
    {
        foreach (PermissionName::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');
            $role->syncPermissions($roleName->defaultPermissions());
        }
    }
}
