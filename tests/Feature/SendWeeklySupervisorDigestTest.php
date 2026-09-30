<?php

namespace Tests\Feature;

use App\Mail\WeeklySupervisorDigest;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendWeeklySupervisorDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_emails_a_supervisor_about_an_expiring_certificate_on_their_direct_report(): void
    {
        Mail::fake();

        $supervisorUser = User::factory()->create();
        $supervisor = Employee::factory()->create(['user_id' => $supervisorUser->id]);
        $report = Employee::factory()->create(['supervisor_id' => $supervisor->id]);
        Certificate::factory()->create([
            'employee_id' => $report->id,
            'expiry_date' => now()->addDays(10),
            'verification_status' => 'verified',
        ]);

        $this->artisan('app:send-weekly-supervisor-digest')->assertSuccessful();

        Mail::assertSent(WeeklySupervisorDigest::class, function (WeeklySupervisorDigest $mail) use ($supervisor, $supervisorUser) {
            return $mail->hasTo($supervisorUser->email)
                && $mail->supervisor->is($supervisor)
                && $mail->expiringCertificates->count() === 1;
        });
    }

    public function test_it_includes_upcoming_training_sessions_for_direct_reports(): void
    {
        Mail::fake();

        $supervisorUser = User::factory()->create();
        $supervisor = Employee::factory()->create(['user_id' => $supervisorUser->id]);
        $report = Employee::factory()->create(['supervisor_id' => $supervisor->id]);

        $session = TrainingSession::factory()->create([
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(3),
            'status' => 'scheduled',
        ]);
        TrainingParticipant::factory()->create(['employee_id' => $report->id, 'training_session_id' => $session->id]);

        $this->artisan('app:send-weekly-supervisor-digest')->assertSuccessful();

        Mail::assertSent(WeeklySupervisorDigest::class, function (WeeklySupervisorDigest $mail) use ($session) {
            return $mail->upcomingSessions->contains(fn ($s) => $s->is($session));
        });
    }

    public function test_it_skips_supervisors_without_a_linked_user_account(): void
    {
        Mail::fake();

        $supervisor = Employee::factory()->create(['user_id' => null]);
        $report = Employee::factory()->create(['supervisor_id' => $supervisor->id]);
        Certificate::factory()->create([
            'employee_id' => $report->id,
            'expiry_date' => now()->addDays(5),
            'verification_status' => 'verified',
        ]);

        $this->artisan('app:send-weekly-supervisor-digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_does_not_email_a_supervisor_whose_team_has_nothing_to_report(): void
    {
        Mail::fake();

        $supervisorUser = User::factory()->create();
        $supervisor = Employee::factory()->create(['user_id' => $supervisorUser->id]);
        Employee::factory()->create(['supervisor_id' => $supervisor->id]);

        $this->artisan('app:send-weekly-supervisor-digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_does_not_email_employees_with_no_direct_reports(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        $this->artisan('app:send-weekly-supervisor-digest')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
