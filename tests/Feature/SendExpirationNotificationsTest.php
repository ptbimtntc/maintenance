<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\CertificateExpiringSoon;
use App\Notifications\UpcomingTrainingSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendExpirationNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_it_notifies_certificate_managers_once_when_a_certificate_enters_the_expiring_window(): void
    {
        Notification::fake();

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::PeopleDevelopment->value);

        $employee = Employee::factory()->create();
        $certificate = Certificate::factory()->create([
            'employee_id' => $employee->id,
            'expiry_date' => now()->addDays(20),
            'verification_status' => 'verified',
        ]);

        $this->artisan('app:send-expiration-notifications')->assertSuccessful();

        Notification::assertSentTo($manager, CertificateExpiringSoon::class);
        $this->assertNotNull($certificate->fresh()->expiry_notified_at);

        Notification::fake();
        $this->artisan('app:send-expiration-notifications')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_it_notifies_the_employees_own_linked_user_too(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        Certificate::factory()->create([
            'employee_id' => $employee->id,
            'expiry_date' => now()->addDays(5),
            'verification_status' => 'verified',
        ]);

        $this->artisan('app:send-expiration-notifications')->assertSuccessful();

        Notification::assertSentTo($user, CertificateExpiringSoon::class);
    }

    public function test_it_reminds_participants_of_upcoming_training_sessions_once(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $session = TrainingSession::factory()->create([
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(3),
            'status' => 'scheduled',
        ]);
        $participant = TrainingParticipant::factory()->create([
            'employee_id' => $employee->id,
            'training_session_id' => $session->id,
        ]);

        $this->artisan('app:send-expiration-notifications')->assertSuccessful();

        Notification::assertSentTo($user, UpcomingTrainingSession::class);
        $this->assertNotNull($participant->fresh()->reminded_at);

        Notification::fake();
        $this->artisan('app:send-expiration-notifications')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_it_does_not_remind_participants_of_sessions_outside_the_window(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $session = TrainingSession::factory()->create([
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(30),
            'status' => 'scheduled',
        ]);
        TrainingParticipant::factory()->create([
            'employee_id' => $employee->id,
            'training_session_id' => $session->id,
        ]);

        $this->artisan('app:send-expiration-notifications')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
