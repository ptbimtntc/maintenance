<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAutoProvisionUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creating_an_employee_automatically_creates_and_links_a_login(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $position = Position::factory()->create(['title' => 'Maintenance Supervisor']);

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-55001',
            'full_name' => 'Budi Baru',
            'position_id' => $position->id,
        ]);

        $response->assertRedirect();

        $employee = Employee::where('employee_number', 'EMP-55001')->firstOrFail();
        $this->assertNotNull($employee->user_id);

        $newUser = $employee->user;
        $this->assertSame('Budi Baru', $newUser->name);
        $this->assertSame('emp-55001', $newUser->username);
        $this->assertTrue($newUser->must_change_password);
        $this->assertTrue($newUser->hasRole(RoleName::MaintenanceSupervisor->value));

        // The NIK also works as both username and starting password.
        $this->post(route('login'), ['email' => 'EMP-55001', 'password' => 'EMP-55001'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_creating_an_employee_skips_auto_provisioning_if_the_nik_is_already_a_login(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        User::factory()->create(['username' => 'emp-55002']);

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-55002',
            'full_name' => 'Budi Bentrok',
        ]);

        $response->assertRedirect();

        $employee = Employee::where('employee_number', 'EMP-55002')->firstOrFail();
        $this->assertNull($employee->user_id);
    }
}
