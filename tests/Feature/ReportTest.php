<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\EmployeeDevelopmentPlan;
use App\Models\TrainingRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_maintenance_staff_cannot_view_reports(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
    }

    public function test_manager_can_view_the_reports_landing_page(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $this->actingAs($manager)->get(route('reports.index'))->assertOk();
    }

    public function test_training_hours_report_sums_hours_per_employee(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $employee = Employee::factory()->create();

        TrainingRecord::factory()->create(['employee_id' => $employee->id, 'duration_hours' => 4]);
        TrainingRecord::factory()->create(['employee_id' => $employee->id, 'duration_hours' => 6]);

        $response = $this->actingAs($manager)->get(route('reports.training-hours'));

        $response->assertOk();
        $response->assertSee('10');
    }

    public function test_training_hours_report_can_be_exported_as_csv(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $employee = Employee::factory()->create();
        TrainingRecord::factory()->create(['employee_id' => $employee->id, 'duration_hours' => 5]);

        $response = $this->actingAs($manager)->get(route('reports.training-hours', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_development_summary_counts_plans_by_status(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        EmployeeDevelopmentPlan::factory()->count(2)->create(['status' => 'in_progress']);
        EmployeeDevelopmentPlan::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($manager)->get(route('reports.development-summary'));

        $response->assertOk();
        $response->assertViewHas('totalPlans', 3);
        $response->assertViewHas('byStatus', fn ($byStatus) => $byStatus['in_progress'] === 2 && $byStatus['completed'] === 1);
    }
}
