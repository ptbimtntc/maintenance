<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function descriptorFor(int $seed): array
    {
        return array_fill(0, 128, $seed / 100);
    }

    public function test_matching_descriptor_checks_in_the_right_participant(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $enrolledUser = User::factory()->create(['face_descriptor' => json_encode($this->descriptorFor(1))]);
        $employee = Employee::factory()->create(['user_id' => $enrolledUser->id]);
        $session = TrainingSession::factory()->create();
        $participant = TrainingParticipant::factory()->create([
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'attendance_status' => 'confirmed',
        ]);

        $response = $this->actingAs($manager)->postJson(route('training.sessions.check-in.attempt', $session), [
            'descriptor' => $this->descriptorFor(1),
        ]);

        $response->assertOk();
        $response->assertJson(['already' => false]);
        $this->assertSame('attended', $participant->fresh()->attendance_status);
        $this->assertNotNull($participant->fresh()->checked_in_at);
    }

    public function test_unmatched_descriptor_is_rejected(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $enrolledUser = User::factory()->create(['face_descriptor' => json_encode($this->descriptorFor(1))]);
        $employee = Employee::factory()->create(['user_id' => $enrolledUser->id]);
        $session = TrainingSession::factory()->create();
        $participant = TrainingParticipant::factory()->create([
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'attendance_status' => 'confirmed',
        ]);

        $response = $this->actingAs($manager)->postJson(route('training.sessions.check-in.attempt', $session), [
            'descriptor' => $this->descriptorFor(99),
        ]);

        $response->assertStatus(422);
        $this->assertSame('confirmed', $participant->fresh()->attendance_status);
    }

    public function test_already_attended_participant_reports_already_checked_in(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $enrolledUser = User::factory()->create(['face_descriptor' => json_encode($this->descriptorFor(1))]);
        $employee = Employee::factory()->create(['user_id' => $enrolledUser->id]);
        $session = TrainingSession::factory()->create();
        TrainingParticipant::factory()->create([
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'attendance_status' => 'attended',
            'checked_in_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($manager)->postJson(route('training.sessions.check-in.attempt', $session), [
            'descriptor' => $this->descriptorFor(1),
        ]);

        $response->assertOk();
        $response->assertJson(['already' => true]);
    }

    public function test_participants_without_face_enrollment_are_ignored(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $employee = Employee::factory()->create(['user_id' => User::factory()->create(['face_descriptor' => null])->id]);
        $session = TrainingSession::factory()->create();
        TrainingParticipant::factory()->create([
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'attendance_status' => 'confirmed',
        ]);

        $response = $this->actingAs($manager)->postJson(route('training.sessions.check-in.attempt', $session), [
            'descriptor' => $this->descriptorFor(1),
        ]);

        $response->assertStatus(422);
    }
}
