<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\TrainingParticipant;
use App\Models\TrainingProgram;
use App\Models\TrainingQuestion;
use App\Models\TrainingRecord;
use App\Models\TrainingSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Builds a participant who has already passed the quiz (submitted,
     * scored, certificate issued, training record logged), returning
     * [admin, participant].
     */
    private function passedParticipant(): array
    {
        $employeeUser = User::factory()->create();
        $employeeUser->assignRole(RoleName::MaintenanceStaff->value);
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);

        $program = TrainingProgram::factory()->create(['passing_score' => 70]);
        $session = TrainingSession::factory()->create([
            'training_program_id' => $program->id,
            'start_date' => now(),
            'end_date' => now(),
        ]);

        $question = TrainingQuestion::create([
            'training_program_id' => $program->id,
            'question_text' => 'What is 1 + 1?',
            'allow_multiple_answers' => false,
        ]);
        $correct = $question->choices()->create(['option_label' => 'A', 'choice_text' => '2', 'is_correct' => true, 'points' => 100]);
        $question->choices()->create(['option_label' => 'B', 'choice_text' => '3', 'is_correct' => false, 'points' => 0]);

        $participant = TrainingParticipant::create([
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'attendance_status' => 'attended',
        ]);

        $this->actingAs($employeeUser)->post(route('training.quiz.submit', $participant), [
            'answers' => [$question->id => $correct->id],
        ]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        return [$admin, $participant->fresh(), $session];
    }

    public function test_admin_can_reset_a_passed_quiz(): void
    {
        [$admin, $participant, $session] = $this->passedParticipant();

        $this->assertNotNull($participant->quiz_submitted_at);
        $this->assertNotNull($participant->certificate_id);
        $certificateId = $participant->certificate_id;

        $response = $this->actingAs($admin)->post(route('training.sessions.participants.quiz-reset', [$session, $participant]));

        $response->assertRedirect();
        $participant->refresh();
        $this->assertNull($participant->quiz_submitted_at);
        $this->assertNull($participant->quiz_score);
        $this->assertNull($participant->certificate_id);
        $this->assertDatabaseMissing('certificates', ['id' => $certificateId]);
        $this->assertSame(0, DB::table('training_quiz_answers')->where('training_participant_id', $participant->id)->count());
        $this->assertDatabaseMissing('training_records', [
            'employee_id' => $participant->employee_id,
            'training_session_id' => $session->id,
            'deleted_at' => null,
        ]);
    }

    public function test_resetting_an_unsubmitted_quiz_is_a_harmless_no_op(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $employee = Employee::factory()->create();
        $session = TrainingSession::factory()->create();
        $participant = TrainingParticipant::factory()->create([
            'employee_id' => $employee->id,
            'training_session_id' => $session->id,
        ]);

        $response = $this->actingAs($admin)->post(route('training.sessions.participants.quiz-reset', [$session, $participant]));

        $response->assertRedirect();
        $response->assertSessionHas('status');
    }

    public function test_maintenance_staff_cannot_reset_a_quiz(): void
    {
        [, $participant, $session] = $this->passedParticipant();

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($staff)->post(route('training.sessions.participants.quiz-reset', [$session, $participant]));

        $response->assertForbidden();
    }
}
