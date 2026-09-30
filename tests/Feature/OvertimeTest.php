<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\EmploymentSource;
use App\Models\EmploymentStatus;
use App\Models\OvertimeEntry;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\OvertimeEditApproved;
use App\Notifications\OvertimeEditRequested;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OvertimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeSupervisorWithReport(): array
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisor = Employee::factory()->create(['user_id' => $supervisorUser->id]);
        $report = Employee::factory()->create(['supervisor_id' => $supervisor->id]);

        return [$supervisorUser, $supervisor, $report];
    }

    public function test_a_user_who_is_not_a_supervisor_and_has_no_manage_permission_cannot_view_overtime(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        Employee::factory()->create(['user_id' => $staff->id]);

        $this->actingAs($staff)->get(route('overtime.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('overtime.create'))->assertForbidden();
    }

    public function test_a_supervisor_can_view_their_own_team_dashboard(): void
    {
        [$supervisorUser, , $report] = $this->makeSupervisorWithReport();
        OvertimeEntry::factory()->create(['employee_id' => $report->id, 'created_by' => $supervisorUser->id]);

        $response = $this->actingAs($supervisorUser)->get(route('overtime.index'));

        $response->assertOk();
        $response->assertSee($report->full_name);
    }

    public function test_a_supervisor_can_log_overtime_only_for_their_own_direct_report(): void
    {
        [$supervisorUser, , $report] = $this->makeSupervisorWithReport();
        $outsider = Employee::factory()->create();

        $response = $this->actingAs($supervisorUser)->post(route('overtime.store'), [
            'employee_id' => $report->id,
            'start_at' => now()->format('Y-m-d H:i'),
            'end_at' => now()->addHours(3)->format('Y-m-d H:i'),
            'remarks' => 'Fixing the conveyor belt.',
            'compensation_type' => 'OT_PAID',
        ]);

        $response->assertRedirect(route('overtime.index'));
        $this->assertDatabaseHas('overtime_entries', [
            'employee_id' => $report->id,
            'status' => OvertimeEntry::STATUS_LOCKED,
        ]);

        $blocked = $this->actingAs($supervisorUser)->post(route('overtime.store'), [
            'employee_id' => $outsider->id,
            'start_at' => now()->format('Y-m-d H:i'),
            'end_at' => now()->addHours(2)->format('Y-m-d H:i'),
            'remarks' => 'Should not be allowed.',
            'compensation_type' => 'OT_PAID',
        ]);

        $blocked->assertForbidden();
    }

    public function test_a_locked_entry_cannot_be_edited_directly(): void
    {
        [$supervisorUser, , $report] = $this->makeSupervisorWithReport();
        $entry = OvertimeEntry::factory()->create(['employee_id' => $report->id, 'created_by' => $supervisorUser->id]);

        $this->actingAs($supervisorUser)->get(route('overtime.edit', $entry))->assertForbidden();
        $this->actingAs($supervisorUser)->put(route('overtime.update', $entry), [
            'employee_id' => $report->id,
            'start_at' => now()->format('Y-m-d H:i'),
            'end_at' => now()->addHour()->format('Y-m-d H:i'),
            'remarks' => 'Trying to sneak an edit in.',
            'compensation_type' => 'OT_PAID',
        ])->assertForbidden();
    }

    public function test_a_supervisor_cannot_act_on_another_supervisors_entry(): void
    {
        [, , $report] = $this->makeSupervisorWithReport();
        $entry = OvertimeEntry::factory()->create(['employee_id' => $report->id]);

        $otherSupervisorUser = User::factory()->create();
        $otherSupervisor = Employee::factory()->create(['user_id' => $otherSupervisorUser->id]);
        Employee::factory()->create(['supervisor_id' => $otherSupervisor->id]);

        $this->actingAs($otherSupervisorUser)->post(route('overtime.request-edit', $entry), [
            'edit_request_reason' => 'Not my team.',
        ])->assertForbidden();
    }

    public function test_requesting_an_edit_notifies_overtime_managers_and_sets_status(): void
    {
        Notification::fake();

        [$supervisorUser, , $report] = $this->makeSupervisorWithReport();
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $entry = OvertimeEntry::factory()->create(['employee_id' => $report->id, 'created_by' => $supervisorUser->id]);

        $this->actingAs($supervisorUser)->post(route('overtime.request-edit', $entry), [
            'edit_request_reason' => 'Wrong end time recorded.',
        ])->assertRedirect(route('overtime.index'));

        $entry->refresh();
        $this->assertSame(OvertimeEntry::STATUS_EDIT_REQUESTED, $entry->status);
        $this->assertSame('Wrong end time recorded.', $entry->edit_request_reason);
        Notification::assertSentTo($hr, OvertimeEditRequested::class);
    }

    public function test_requesting_an_edit_twice_is_rejected(): void
    {
        [$supervisorUser, , $report] = $this->makeSupervisorWithReport();
        $entry = OvertimeEntry::factory()->create([
            'employee_id' => $report->id,
            'created_by' => $supervisorUser->id,
            'status' => OvertimeEntry::STATUS_EDIT_REQUESTED,
        ]);

        $this->actingAs($supervisorUser)->post(route('overtime.request-edit', $entry), [
            'edit_request_reason' => 'Again please.',
        ])->assertStatus(422);
    }

    public function test_non_manager_cannot_approve_or_reject_edit_requests(): void
    {
        [$supervisorUser, , $report] = $this->makeSupervisorWithReport();
        $entry = OvertimeEntry::factory()->create([
            'employee_id' => $report->id,
            'status' => OvertimeEntry::STATUS_EDIT_REQUESTED,
        ]);

        $this->actingAs($supervisorUser)->post(route('overtime.approve-edit', $entry))->assertForbidden();
        $this->actingAs($supervisorUser)->post(route('overtime.reject-edit', $entry))->assertForbidden();
    }

    public function test_a_manager_can_approve_an_edit_request_and_the_supervisor_can_then_edit_once(): void
    {
        Notification::fake();

        [$supervisorUser, , $report] = $this->makeSupervisorWithReport();
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $entry = OvertimeEntry::factory()->create([
            'employee_id' => $report->id,
            'created_by' => $supervisorUser->id,
            'status' => OvertimeEntry::STATUS_EDIT_REQUESTED,
            'edit_request_reason' => 'Wrong hours.',
        ]);

        $this->actingAs($hr)->post(route('overtime.approve-edit', $entry))->assertRedirect();

        $entry->refresh();
        $this->assertSame(OvertimeEntry::STATUS_EDIT_APPROVED, $entry->status);
        $this->assertSame($hr->id, $entry->edit_approved_by);
        Notification::assertSentTo($supervisorUser, OvertimeEditApproved::class);

        $this->actingAs($supervisorUser)->get(route('overtime.edit', $entry))->assertOk();

        $this->actingAs($supervisorUser)->put(route('overtime.update', $entry), [
            'employee_id' => $report->id,
            'start_at' => now()->format('Y-m-d H:i'),
            'end_at' => now()->addHours(4)->format('Y-m-d H:i'),
            'remarks' => 'Corrected the hours.',
            'compensation_type' => 'OT_LEAVE',
        ])->assertRedirect(route('overtime.index'));

        $entry->refresh();
        $this->assertSame(OvertimeEntry::STATUS_LOCKED, $entry->status);
        $this->assertNull($entry->edit_approved_by);
        $this->assertSame('OT_LEAVE', $entry->compensation_type);

        // Spending the approval re-locks it - editing again needs a new request.
        $this->actingAs($supervisorUser)->get(route('overtime.edit', $entry))->assertForbidden();
    }

    public function test_a_manager_can_reject_an_edit_request(): void
    {
        [$supervisorUser, , $report] = $this->makeSupervisorWithReport();
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $entry = OvertimeEntry::factory()->create([
            'employee_id' => $report->id,
            'status' => OvertimeEntry::STATUS_EDIT_REQUESTED,
            'edit_request_reason' => 'Wrong hours.',
        ]);

        $this->actingAs($hr)->post(route('overtime.reject-edit', $entry))->assertRedirect();

        $entry->refresh();
        $this->assertSame(OvertimeEntry::STATUS_LOCKED, $entry->status);
        $this->assertNull($entry->edit_request_reason);
    }

    public function test_only_overtime_managers_can_export_or_mark_submitted(): void
    {
        [$supervisorUser] = $this->makeSupervisorWithReport();

        $this->actingAs($supervisorUser)->get(route('overtime.export'))->assertForbidden();
        $this->actingAs($supervisorUser)->post(route('overtime.mark-submitted'))->assertForbidden();
    }

    public function test_a_manager_can_export_overtime_entries_as_xlsx(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);
        [, , $report] = $this->makeSupervisorWithReport();
        OvertimeEntry::factory()->create(['employee_id' => $report->id]);

        $response = $this->actingAs($hr)->get(route('overtime.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_the_create_form_only_lists_active_employees_and_defaults_to_the_bekaert_source(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $bekaert = EmploymentSource::factory()->create(['name' => 'Bekaert']);
        $otherSource = EmploymentSource::factory()->create();
        $activeStatus = EmploymentStatus::factory()->create(['counts_as_active' => true]);
        $inactiveStatus = EmploymentStatus::factory()->create(['counts_as_active' => false]);

        $activeBekaert = Employee::factory()->create(['full_name' => 'Active Bekaert Person', 'employment_source_id' => $bekaert->id, 'employment_status_id' => $activeStatus->id]);
        $inactiveBekaert = Employee::factory()->create(['full_name' => 'Inactive Bekaert Person', 'employment_source_id' => $bekaert->id, 'employment_status_id' => $inactiveStatus->id]);
        $activeOtherSource = Employee::factory()->create(['full_name' => 'Active Other Source Person', 'employment_source_id' => $otherSource->id, 'employment_status_id' => $activeStatus->id]);

        $response = $this->actingAs($admin)->get(route('overtime.create'));

        $response->assertOk();
        $response->assertSee('Active Bekaert Person');
        $response->assertDontSee('Inactive Bekaert Person');
        $response->assertDontSee('Active Other Source Person');
    }

    public function test_the_create_form_can_be_filtered_by_shift(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $bekaert = EmploymentSource::factory()->create(['name' => 'Bekaert']);
        $activeStatus = EmploymentStatus::factory()->create(['counts_as_active' => true]);
        $morningShift = Shift::factory()->create();
        $nightShift = Shift::factory()->create();

        Employee::factory()->create(['full_name' => 'Morning Person', 'employment_source_id' => $bekaert->id, 'employment_status_id' => $activeStatus->id, 'shift_id' => $morningShift->id]);
        Employee::factory()->create(['full_name' => 'Night Person', 'employment_source_id' => $bekaert->id, 'employment_status_id' => $activeStatus->id, 'shift_id' => $nightShift->id]);

        $response = $this->actingAs($admin)->get(route('overtime.create', ['shift_id' => $morningShift->id]));

        $response->assertOk();
        $response->assertSee('Morning Person');
        $response->assertDontSee('Night Person');
    }

    public function test_an_overtime_manager_can_log_an_entry_for_any_employee_without_being_their_supervisor(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $employee = Employee::factory()->create();

        $response = $this->actingAs($admin)->post(route('overtime.store'), [
            'employee_id' => $employee->id,
            'start_at' => now()->format('Y-m-d H:i'),
            'end_at' => now()->addHours(2)->format('Y-m-d H:i'),
            'remarks' => 'Logged directly by admin.',
            'compensation_type' => 'OT_PAID',
        ]);

        $response->assertRedirect(route('overtime.index'));
        $this->assertDatabaseHas('overtime_entries', ['employee_id' => $employee->id, 'created_by' => $admin->id]);
    }

    public function test_an_overtime_manager_can_edit_any_entry_even_while_locked(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        [, , $report] = $this->makeSupervisorWithReport();
        $entry = OvertimeEntry::factory()->create(['employee_id' => $report->id, 'status' => OvertimeEntry::STATUS_LOCKED]);

        $this->actingAs($admin)->get(route('overtime.edit', $entry))->assertOk();

        $this->actingAs($admin)->put(route('overtime.update', $entry), [
            'employee_id' => $report->id,
            'start_at' => now()->format('Y-m-d H:i'),
            'end_at' => now()->addHours(5)->format('Y-m-d H:i'),
            'remarks' => 'Corrected directly by admin.',
            'compensation_type' => 'OT_LEAVE',
        ])->assertRedirect(route('overtime.index'));

        $entry->refresh();
        $this->assertSame('OT_LEAVE', $entry->compensation_type);
        $this->assertSame(OvertimeEntry::STATUS_LOCKED, $entry->status);
    }

    public function test_a_manager_can_mark_selected_entries_as_submitted_to_hr(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);
        [, , $report] = $this->makeSupervisorWithReport();
        $entry = OvertimeEntry::factory()->create(['employee_id' => $report->id]);
        $other = OvertimeEntry::factory()->create(['employee_id' => $report->id]);

        $this->actingAs($hr)->post(route('overtime.mark-submitted'), [
            'overtime_entry_ids' => [$entry->id],
        ])->assertRedirect();

        $this->assertNotNull($entry->fresh()->submitted_to_hr_at);
        $this->assertNull($other->fresh()->submitted_to_hr_at);
    }
}
