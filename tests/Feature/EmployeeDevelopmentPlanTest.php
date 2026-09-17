<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\EmployeeDevelopmentPlan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDevelopmentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_development_plans(): void
    {
        $response = $this->get(route('development-plans.index'));

        $response->assertRedirect('/login');
    }

    public function test_manager_can_create_a_development_plan(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $managerEmployee = Employee::factory()->create(['user_id' => $manager->id]);
        $employee = Employee::factory()->create(['supervisor_id' => $managerEmployee->id]);

        $response = $this->actingAs($manager)->post(route('employees.development-plans.store', $employee), [
            'development_objective' => 'Improve PLC troubleshooting skills',
            'development_action' => 'ojt',
            'priority' => 'high',
            'status' => 'not_started',
            'progress_percentage' => 0,
        ]);

        $response->assertRedirect(route('employees.show', $employee));
        $this->assertDatabaseHas('employee_development_plans', [
            'employee_id' => $employee->id,
            'development_objective' => 'Improve PLC troubleshooting skills',
            'created_by' => $manager->id,
        ]);
    }

    public function test_maintenance_staff_cannot_create_a_development_plan(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $employee = Employee::factory()->create(['user_id' => $staff->id]);

        $response = $this->actingAs($staff)->post(route('employees.development-plans.store', $employee), [
            'development_objective' => 'Self-requested plan',
            'development_action' => 'self_learning',
            'priority' => 'low',
            'status' => 'not_started',
            'progress_percentage' => 0,
        ]);

        $response->assertForbidden();
    }

    public function test_plan_from_another_employee_cannot_be_edited_via_mismatched_route(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $managerEmployee = Employee::factory()->create(['user_id' => $manager->id]);
        $employeeA = Employee::factory()->create(['supervisor_id' => $managerEmployee->id]);
        $employeeB = Employee::factory()->create(['supervisor_id' => $managerEmployee->id]);
        $plan = EmployeeDevelopmentPlan::factory()->create(['employee_id' => $employeeA->id]);

        $response = $this->actingAs($manager)->get(route('employees.development-plans.edit', [$employeeB, $plan]));

        $response->assertNotFound();
    }
}
