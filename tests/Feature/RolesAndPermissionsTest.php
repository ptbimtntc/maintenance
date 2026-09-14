<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_role_has_full_permission_set(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);

        $this->assertTrue($user->hasPermissionTo(PermissionName::ManageUsers->value));
        $this->assertTrue($user->hasPermissionTo(PermissionName::ManageEmployees->value));
    }

    public function test_maintenance_staff_cannot_manage_employees(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleName::MaintenanceStaff->value);

        $this->assertTrue($user->hasPermissionTo(PermissionName::ViewOwnEmployee->value));
        $this->assertFalse($user->hasPermissionTo(PermissionName::ManageEmployees->value));
    }

    public function test_user_without_a_role_has_no_module_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();

        $this->assertFalse($user->hasPermissionTo(PermissionName::ViewOwnEmployee->value));
    }
}
