<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\MaintenanceTeam;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_employees(): void
    {
        $response = $this->get(route('employees.index'));

        $response->assertRedirect('/login');
    }

    public function test_user_without_any_employee_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('employees.index'));

        $response->assertForbidden();
    }

    public function test_administrator_sees_all_employees(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        Employee::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 3);
    }

    public function test_maintenance_staff_can_only_see_their_own_employee_record(): void
    {
        $staffUser = User::factory()->create();
        $staffUser->assignRole(RoleName::MaintenanceStaff->value);

        $ownEmployee = Employee::factory()->create(['user_id' => $staffUser->id]);
        Employee::factory()->count(2)->create();

        $response = $this->actingAs($staffUser)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 1
            && $employees->first()->id === $ownEmployee->id);
    }

    public function test_maintenance_staff_cannot_view_another_employees_profile(): void
    {
        $staffUser = User::factory()->create();
        $staffUser->assignRole(RoleName::MaintenanceStaff->value);
        Employee::factory()->create(['user_id' => $staffUser->id]);

        $otherEmployee = Employee::factory()->create();

        $response = $this->actingAs($staffUser)->get(route('employees.show', $otherEmployee));

        $response->assertForbidden();
    }

    public function test_supervisor_only_sees_employees_on_their_own_team(): void
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);

        $teamA = MaintenanceTeam::factory()->create();
        $teamB = MaintenanceTeam::factory()->create();

        Employee::factory()->create(['user_id' => $supervisorUser->id, 'maintenance_team_id' => $teamA->id]);
        Employee::factory()->create(['maintenance_team_id' => $teamA->id]);
        Employee::factory()->create(['maintenance_team_id' => $teamB->id]);

        $response = $this->actingAs($supervisorUser)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 2);
    }

    public function test_maintenance_staff_cannot_create_employees(): void
    {
        $staffUser = User::factory()->create();
        $staffUser->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($staffUser)->get(route('employees.create'));

        $response->assertForbidden();
    }

    public function test_administrator_can_create_an_employee(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-99999',
            'full_name' => 'Test Employee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-99999']);
    }

    public function test_administrator_can_delete_an_employee(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $employee = Employee::factory()->create();

        $response = $this->actingAs($admin)->delete(route('employees.destroy', $employee));

        $response->assertRedirect(route('employees.index'));
        $this->assertSoftDeleted($employee);
    }
}
