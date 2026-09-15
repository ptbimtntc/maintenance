<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_administrators_can_view_the_audit_log(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $this->actingAs($manager)->get(route('audit-logs.index'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
    }

    public function test_creating_an_employee_writes_an_audit_log_entry(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-AUDIT1',
            'full_name' => 'Audit Test Employee',
        ]);

        $employee = Employee::where('employee_number', 'EMP-AUDIT1')->first();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
            'action' => 'created',
            'user_id' => $admin->id,
        ]);
    }

    public function test_updating_a_record_logs_only_the_changed_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $employee = Employee::factory()->create(['full_name' => 'Original Name']);

        $employee->update(['full_name' => 'Updated Name']);

        $log = AuditLog::where('auditable_id', $employee->id)->where('action', 'updated')->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('full_name', $log->changes);
        $this->assertArrayNotHasKey('updated_at', $log->changes);
    }

    public function test_deleting_a_record_writes_a_deleted_audit_entry(): void
    {
        $employee = Employee::factory()->create();
        $employeeId = $employee->id;

        $employee->delete();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Employee::class,
            'auditable_id' => $employeeId,
            'action' => 'deleted',
        ]);
    }
}
