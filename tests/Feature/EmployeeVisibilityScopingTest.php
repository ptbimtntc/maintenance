<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\EmployeeDevelopmentPlan;
use App\Models\TrainingRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Employee::scopeVisibleTo() is the single source of truth for "who can see
 * whom" and is reused across Certificates, Training Records, Development
 * Plans, Skill Matrix, and Competency Gap Analysis - these tests confirm
 * that reuse actually scopes each of those screens, not just the Employee
 * list itself (which EmployeeManagementTest already covers in detail).
 */
class EmployeeVisibilityScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function supervisorWithReportAndStranger(): array
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisorEmployee = Employee::factory()->create(['user_id' => $supervisorUser->id]);

        $directReport = Employee::factory()->create(['supervisor_id' => $supervisorEmployee->id]);
        $stranger = Employee::factory()->create();

        return [$supervisorUser, $supervisorEmployee, $directReport, $stranger];
    }

    public function test_certificates_index_is_scoped_to_visible_employees(): void
    {
        [$supervisorUser, , $directReport, $stranger] = $this->supervisorWithReportAndStranger();

        Certificate::factory()->create(['employee_id' => $directReport->id, 'name' => 'Visible Cert']);
        Certificate::factory()->create(['employee_id' => $stranger->id, 'name' => 'Hidden Cert']);

        $response = $this->actingAs($supervisorUser)->get(route('certificates.index'));

        $response->assertOk();
        $response->assertSee('Visible Cert');
        $response->assertDontSee('Hidden Cert');
    }

    public function test_training_records_index_is_scoped_to_visible_employees(): void
    {
        [$supervisorUser, , $directReport, $stranger] = $this->supervisorWithReportAndStranger();

        TrainingRecord::factory()->create(['employee_id' => $directReport->id]);
        TrainingRecord::factory()->create(['employee_id' => $stranger->id]);

        $response = $this->actingAs($supervisorUser)->get(route('training.records.index'));

        $response->assertOk();
        $response->assertViewHas('records', fn ($records) => $records->total() === 1
            && $records->first()->employee_id === $directReport->id);
    }

    public function test_development_plans_index_is_scoped_to_visible_employees(): void
    {
        [$supervisorUser, , $directReport, $stranger] = $this->supervisorWithReportAndStranger();

        EmployeeDevelopmentPlan::factory()->create(['employee_id' => $directReport->id]);
        EmployeeDevelopmentPlan::factory()->create(['employee_id' => $stranger->id]);

        $response = $this->actingAs($supervisorUser)->get(route('development-plans.index'));

        $response->assertOk();
        $response->assertViewHas('plans', fn ($plans) => $plans->total() === 1
            && $plans->first()->employee_id === $directReport->id);
    }

    public function test_skill_matrix_is_scoped_to_visible_employees(): void
    {
        [$supervisorUser, , $directReport, $stranger] = $this->supervisorWithReportAndStranger();

        $response = $this->actingAs($supervisorUser)->get(route('skill-matrix.index'));

        $response->assertOk();
        $response->assertViewHas('employees', function ($employees) use ($directReport, $stranger) {
            $ids = $employees->pluck('id')->all();

            return in_array($directReport->id, $ids) && ! in_array($stranger->id, $ids);
        });
    }

    public function test_competency_gap_analysis_is_scoped_to_visible_employees(): void
    {
        [$supervisorUser, , , $stranger] = $this->supervisorWithReportAndStranger();

        $response = $this->actingAs($supervisorUser)->get(route('competency-gap-analysis.index'));

        $response->assertOk();
        // The stranger should never surface in the gap drill-down list.
        $response->assertDontSee($stranger->full_name);
    }

    public function test_a_supervisor_cannot_add_a_certificate_for_someone_outside_their_visibility(): void
    {
        [$supervisorUser, , , $stranger] = $this->supervisorWithReportAndStranger();

        $response = $this->actingAs($supervisorUser)->get(route('employees.certificates.create', $stranger));

        $response->assertForbidden();
    }
}
