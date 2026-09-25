<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecertificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_the_recertification_report(): void
    {
        $this->get(route('certificates.recertification'))->assertRedirect('/guest-login');
    }

    public function test_default_window_shows_certificates_due_within_60_days_including_overdue(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $employee = Employee::factory()->create();
        $overdue = Certificate::factory()->create([
            'employee_id' => $employee->id,
            'name' => 'Overdue Cert',
            'verification_status' => 'verified',
            'expiry_date' => now()->subDays(5),
        ]);
        $dueSoon = Certificate::factory()->create([
            'employee_id' => $employee->id,
            'name' => 'Due Soon Cert',
            'verification_status' => 'verified',
            'expiry_date' => now()->addDays(45),
        ]);
        $farOut = Certificate::factory()->create([
            'employee_id' => $employee->id,
            'name' => 'Far Out Cert',
            'verification_status' => 'verified',
            'expiry_date' => now()->addDays(200),
        ]);

        $response = $this->actingAs($hr)->get(route('certificates.recertification'));

        $response->assertOk();
        $response->assertSee('Overdue Cert');
        $response->assertSee('Due Soon Cert');
        $response->assertDontSee('Far Out Cert');
    }

    public function test_expired_window_only_shows_overdue_certificates(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $employee = Employee::factory()->create();
        Certificate::factory()->create([
            'employee_id' => $employee->id,
            'name' => 'Overdue Cert',
            'verification_status' => 'verified',
            'expiry_date' => now()->subDays(5),
        ]);
        Certificate::factory()->create([
            'employee_id' => $employee->id,
            'name' => 'Due Soon Cert',
            'verification_status' => 'verified',
            'expiry_date' => now()->addDays(10),
        ]);

        $response = $this->actingAs($hr)->get(route('certificates.recertification', ['window' => 'expired']));

        $response->assertOk();
        $response->assertSee('Overdue Cert');
        $response->assertDontSee('Due Soon Cert');
    }

    public function test_unverified_certificates_never_appear_in_the_report(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $employee = Employee::factory()->create();
        Certificate::factory()->create([
            'employee_id' => $employee->id,
            'name' => 'Pending Cert',
            'verification_status' => 'pending_verification',
            'expiry_date' => now()->subDays(5),
        ]);

        $response = $this->actingAs($hr)->get(route('certificates.recertification', ['window' => 'all']));

        $response->assertOk();
        $response->assertDontSee('Pending Cert');
    }
}
