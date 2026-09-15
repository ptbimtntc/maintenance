<?php

namespace Tests\Feature;

use App\Enums\MenuKey;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuEditPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_menus_default_to_read_only_for_a_role_with_no_manage_permission(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->assertFalse($staff->canEditMenu(MenuKey::Employees));
        $this->assertFalse($staff->canEditMenu(MenuKey::Certificates));
        $this->assertFalse($staff->canEditMenu(MenuKey::Training));
    }

    public function test_job_descriptions_is_editable_by_default_for_everyone(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->assertTrue($staff->canEditMenu(MenuKey::JobDescriptions));
    }

    public function test_a_role_that_already_holds_the_manage_permission_can_edit_by_default(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $this->assertTrue($manager->canEditMenu(MenuKey::Employees));
    }

    public function test_an_administrator_can_grant_edit_rights_on_a_menu_the_users_role_lacks(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $staff->menuPermissions()->create(['menu_key' => MenuKey::Employees->value, 'can_edit' => true]);

        $this->assertTrue($staff->fresh()->canEditMenu(MenuKey::Employees));
    }

    public function test_an_administrator_can_revoke_edit_rights_the_users_role_would_otherwise_grant(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $manager->menuPermissions()->create(['menu_key' => MenuKey::Employees->value, 'can_edit' => false]);

        $this->assertFalse($manager->fresh()->canEditMenu(MenuKey::Employees));
    }

    public function test_administrators_can_always_edit_every_menu_regardless_of_overrides(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $admin->menuPermissions()->create(['menu_key' => MenuKey::Employees->value, 'can_edit' => false]);

        $this->assertTrue($admin->fresh()->canEditMenu(MenuKey::Employees));
    }

    public function test_a_revoked_employees_edit_permission_blocks_the_actual_route(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $manager->menuPermissions()->create(['menu_key' => MenuKey::Employees->value, 'can_edit' => false]);

        $employee = Employee::factory()->create();

        $this->actingAs($manager)->get(route('employees.edit', $employee))->assertForbidden();
    }

    public function test_a_granted_certificates_edit_permission_allows_a_role_without_manage_certificates(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisor->menuPermissions()->create(['menu_key' => MenuKey::Certificates->value, 'can_edit' => true]);

        $employee = Employee::factory()->create(['user_id' => $supervisor->id]);

        $this->actingAs($supervisor)->get(route('employees.certificates.create', $employee))->assertOk();
    }
}
