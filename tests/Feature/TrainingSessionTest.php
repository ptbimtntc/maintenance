<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_session_end_date_must_not_be_before_start_date(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $program = TrainingProgram::factory()->create();

        $response = $this->actingAs($manager)->post(route('training.programs.sessions.store', $program), [
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(1)->format('Y-m-d'),
            'status' => 'scheduled',
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_manager_can_add_a_participant_to_a_session(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $session = TrainingSession::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($manager)->post(route('training.sessions.participants.store', $session), [
            'employee_id' => $employee->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('training_participants', [
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_cannot_add_participant_beyond_max_participants(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $session = TrainingSession::factory()->create(['max_participants' => 1]);
        $existing = Employee::factory()->create();
        $session->participants()->create(['employee_id' => $existing->id]);

        $newEmployee = Employee::factory()->create();

        $response = $this->actingAs($manager)->post(route('training.sessions.participants.store', $session), [
            'employee_id' => $newEmployee->id,
        ]);

        $response->assertSessionHasErrors('employee_id');
        $this->assertDatabaseMissing('training_participants', ['employee_id' => $newEmployee->id]);
    }

    public function test_cannot_add_the_same_employee_twice_to_a_session(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $session = TrainingSession::factory()->create();
        $employee = Employee::factory()->create();
        $session->participants()->create(['employee_id' => $employee->id]);

        $response = $this->actingAs($manager)->post(route('training.sessions.participants.store', $session), [
            'employee_id' => $employee->id,
        ]);

        $response->assertSessionHasErrors('employee_id');
    }
}
