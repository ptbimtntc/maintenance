<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_hr_can_record_a_completed_training_for_an_employee(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hr)->post(route('employees.training-records.store', $employee), [
            'training_date' => now()->format('Y-m-d'),
            'duration_hours' => 8,
            'attendance_status' => 'attended',
            'completion_status' => 'completed',
        ]);

        $response->assertRedirect(route('employees.show', $employee));
        $this->assertDatabaseHas('training_records', [
            'employee_id' => $employee->id,
            'duration_hours' => 8,
            'recorded_by' => $hr->id,
        ]);
    }

    public function test_maintenance_staff_cannot_record_training(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $employee = Employee::factory()->create(['user_id' => $staff->id]);

        $response = $this->actingAs($staff)->post(route('employees.training-records.store', $employee), [
            'training_date' => now()->format('Y-m-d'),
            'attendance_status' => 'attended',
            'completion_status' => 'completed',
        ]);

        $response->assertForbidden();
    }

    public function test_employee_profile_shows_total_training_hours(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $managerEmployee = Employee::factory()->create(['user_id' => $manager->id]);
        $employee = Employee::factory()->create(['supervisor_id' => $managerEmployee->id]);

        $employee->trainingRecords()->create([
            'training_date' => now()->subMonth(),
            'duration_hours' => 4,
            'attendance_status' => 'attended',
            'completion_status' => 'completed',
        ]);
        $employee->trainingRecords()->create([
            'training_date' => now(),
            'duration_hours' => 6,
            'attendance_status' => 'attended',
            'completion_status' => 'completed',
        ]);

        $response = $this->actingAs($manager)->get(route('employees.show', $employee));

        $response->assertOk();
        $response->assertSee('Total training hours recorded: 10');
    }

    public function test_the_records_list_can_be_filtered_by_more_than_one_completion_status(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $completed = Employee::factory()->create(['full_name' => 'Completed Carl']);
        $completed->trainingRecords()->create(['training_date' => now(), 'duration_hours' => 2, 'attendance_status' => 'attended', 'completion_status' => 'completed']);
        $failed = Employee::factory()->create(['full_name' => 'Failed Fay']);
        $failed->trainingRecords()->create(['training_date' => now(), 'duration_hours' => 2, 'attendance_status' => 'attended', 'completion_status' => 'failed']);
        $incomplete = Employee::factory()->create(['full_name' => 'Incomplete Ike']);
        $incomplete->trainingRecords()->create(['training_date' => now(), 'duration_hours' => 2, 'attendance_status' => 'attended', 'completion_status' => 'incomplete']);

        $response = $this->actingAs($hr)->get(route('training.records.index', [
            'completion_status' => ['completed', 'failed'],
        ]));

        $response->assertOk();
        $response->assertSee('Completed Carl')->assertSee('Failed Fay')->assertDontSee('Incomplete Ike');
    }
}
