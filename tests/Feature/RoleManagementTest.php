<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        return $admin;
    }

    public function test_only_an_administrator_can_reach_roles_and_permissions(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->actingAs($staff)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.roles.index'))->assertOk();
    }

    public function test_updating_a_role_replaces_its_permission_set(): void
    {
        $role = Role::where('name', RoleName::MaintenanceStaff->value)->firstOrFail();

        $this->actingAs($this->admin())->put(route('admin.roles.update', $role), [
            'permissions' => [PermissionName::ViewOwnEmployee->value, PermissionName::ViewTraining->value],
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo(PermissionName::ViewOwnEmployee->value));
        $this->assertTrue($role->hasPermissionTo(PermissionName::ViewTraining->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::ViewJobDescriptions->value));
    }

    public function test_bulk_reset_restores_every_role_to_its_default_permission_set(): void
    {
        $staffRole = Role::where('name', RoleName::MaintenanceStaff->value)->firstOrFail();
        $staffRole->syncPermissions([PermissionName::ManageUsers->value]);

        $this->actingAs($this->admin())->post(route('admin.roles.reset-defaults'), [])
            ->assertRedirect(route('admin.roles.index'));

        $staffRole->refresh();
        $this->assertEqualsCanonicalizing(
            RoleName::MaintenanceStaff->defaultPermissions(),
            $staffRole->permissions->pluck('name')->all()
        );
    }

    public function test_bulk_reset_can_target_a_single_selected_role(): void
    {
        $staffRole = Role::where('name', RoleName::MaintenanceStaff->value)->firstOrFail();
        $managerRole = Role::where('name', RoleName::MaintenanceManager->value)->firstOrFail();

        $staffRole->syncPermissions([PermissionName::ManageUsers->value]);
        $managerRole->syncPermissions([PermissionName::ManageUsers->value]);

        $this->actingAs($this->admin())->post(route('admin.roles.reset-defaults'), [
            'roles' => [RoleName::MaintenanceStaff->value],
        ]);

        $staffRole->refresh();
        $managerRole->refresh();

        $this->assertEqualsCanonicalizing(
            RoleName::MaintenanceStaff->defaultPermissions(),
            $staffRole->permissions->pluck('name')->all()
        );
        // Untouched: manager's temporary override should survive since it wasn't selected.
        $this->assertEqualsCanonicalizing([PermissionName::ManageUsers->value], $managerRole->permissions->pluck('name')->all());
    }
}
