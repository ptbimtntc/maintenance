<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
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

    public function test_people_development_sees_all_employees(): void
    {
        // People Development (HR) is an intentional exception to hierarchy
        // scoping - it's a cross-organization role, not a line-management
        // one, so it keeps ViewAllEmployees like Administrator.
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        Employee::factory()->count(3)->create();

        $response = $this->actingAs($hr)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 3);
    }

    public function test_manager_sees_only_themselves_and_their_direct_reports(): void
    {
        // Maintenance Manager is scoped by hierarchy like Supervisor - it's
        // a line-management role, not a cross-organization one like People
        // Development.
        $managerUser = User::factory()->create();
        $managerUser->assignRole(RoleName::MaintenanceManager->value);
        $managerEmployee = Employee::factory()->create(['user_id' => $managerUser->id]);

        $directReport = Employee::factory()->create(['supervisor_id' => $managerEmployee->id]);
        Employee::factory()->create();

        $response = $this->actingAs($managerUser)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', function ($employees) use ($managerEmployee, $directReport) {
            $ids = $employees->pluck('id')->all();

            return $employees->total() === 2
                && in_array($managerEmployee->id, $ids)
                && in_array($directReport->id, $ids);
        });
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

    public function test_supervisor_sees_only_themselves_and_their_direct_reports(): void
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisorEmployee = Employee::factory()->create(['user_id' => $supervisorUser->id]);

        $directReport = Employee::factory()->create(['supervisor_id' => $supervisorEmployee->id]);
        // A report-of-a-report: not visible, since visibility is one level
        // deep only (direct reports), not the whole chain beneath them.
        Employee::factory()->create(['supervisor_id' => $directReport->id]);
        // Someone else's employee entirely: never visible.
        Employee::factory()->create();

        $response = $this->actingAs($supervisorUser)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', function ($employees) use ($supervisorEmployee, $directReport) {
            $ids = $employees->pluck('id')->all();

            return $employees->total() === 2
                && in_array($supervisorEmployee->id, $ids)
                && in_array($directReport->id, $ids);
        });
    }

    public function test_supervisor_with_no_direct_reports_sees_only_themselves(): void
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisorEmployee = Employee::factory()->create(['user_id' => $supervisorUser->id]);

        Employee::factory()->count(2)->create();

        $response = $this->actingAs($supervisorUser)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 1
            && $employees->first()->id === $supervisorEmployee->id);
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

    public function test_administrator_can_create_an_employee_with_the_new_classification_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $businessUnit = \App\Models\BusinessUnit::factory()->create();
        $skillPosition = \App\Models\SkillPosition::factory()->create();
        $employmentSource = \App\Models\EmploymentSource::factory()->create();

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-88888',
            'full_name' => 'Classified Employee',
            'business_unit_id' => $businessUnit->id,
            'skill_position_id' => $skillPosition->id,
            'employment_source_id' => $employmentSource->id,
            'workforce_category' => 'BC',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-88888',
            'business_unit_id' => $businessUnit->id,
            'skill_position_id' => $skillPosition->id,
            'employment_source_id' => $employmentSource->id,
            'workforce_category' => 'BC',
        ]);
    }

    public function test_creating_an_employee_rejects_an_invalid_workforce_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-77777',
            'full_name' => 'Bad Category Employee',
            'workforce_category' => 'NOT-A-REAL-CATEGORY',
        ]);

        $response->assertSessionHasErrors('workforce_category');
        $this->assertDatabaseMissing('employees', ['employee_number' => 'EMP-77777']);
    }

    public function test_administrator_can_upload_a_photo_when_creating_an_employee(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-66666',
            'full_name' => 'Photo Employee',
            'photo' => \Illuminate\Http\UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'),
        ]);

        $response->assertRedirect();
        $employee = Employee::where('employee_number', 'EMP-66666')->firstOrFail();
        $this->assertNotNull($employee->photo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($employee->photo_path);
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
