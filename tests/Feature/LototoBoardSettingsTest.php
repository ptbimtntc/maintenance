<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LototoActivity;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\SpareLock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpareLockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LototoBoardSettingsTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $businessUnit;

    private EmploymentStatus $activeStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SpareLockSeeder::class);
        $this->businessUnit = BusinessUnit::factory()->create(['id' => 1]);
        $board = LototoBoard::query()->create([
            'business_unit_id' => $this->businessUnit->id,
            'division_name' => 'Maintenance',
            'code' => 'mtn_tr',
            'title' => 'MAINTENANCE · TIRE CORD',
            'subtitle' => 'Daftar LOTOTO karyawan dan spare',
            'layout' => ['technician_columns' => 20, 'supervisor_columns' => 10, 'section_order' => ['technician', 'other', 'spare']],
            'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true],
            'is_active' => true,
        ]);
        LototoBoardSlot::query()->whereNull('board_id')->update(['board_id' => $board->id]);
        SpareLock::query()->whereNull('board_id')->update(['board_id' => $board->id]);
        $this->activeStatus = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
    }

    public function test_only_administrators_can_open_board_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->actingAs($admin)
            ->get(route('safety.lototo.settings'))
            ->assertOk()
            ->assertSee('Add Position')
            ->assertSee('Save Changes');

        $this->actingAs($staff)
            ->get(route('safety.lototo.settings'))
            ->assertForbidden();
    }

    public function test_technician_positions_can_be_swapped_without_changing_supervisor_positions(): void
    {
        $admin = $this->administrator();
        $technicianOne = $this->employee('01');
        $technicianTwo = $this->employee('02');
        $supervisor = $this->employee('101');
        $slotOne = $this->slot('technician', 1);
        $slotTwo = $this->slot('technician', 2);
        $supervisorSlot = $this->slot('supervisor', 1);

        $slotOne->update(['employee_id' => $technicianOne->id]);
        $slotTwo->update(['employee_id' => $technicianTwo->id]);
        $supervisorSlot->update(['employee_id' => $supervisor->id]);

        $payload = $this->slotPayload('technician', 'employee_id');
        $payload['slots'][$slotOne->id]['employee_id'] = $technicianTwo->id;
        $payload['slots'][$slotTwo->id]['employee_id'] = $technicianOne->id;

        $this->actingAs($admin)
            ->put(route('safety.lototo.settings.update', 'technician'), $payload)
            ->assertRedirect(route('safety.lototo.settings'));

        $this->assertDatabaseHas('lototo_board_slots', [
            'id' => $slotOne->id,
            'employee_id' => $technicianTwo->id,
        ]);
        $this->assertDatabaseHas('lototo_board_slots', [
            'id' => $slotTwo->id,
            'employee_id' => $technicianOne->id,
        ]);
        $this->assertDatabaseHas('lototo_board_slots', [
            'id' => $supervisorSlot->id,
            'employee_id' => $supervisor->id,
        ]);
    }

    public function test_supervisor_positions_can_be_swapped_without_changing_technician_positions(): void
    {
        $admin = $this->administrator();
        $technician = $this->employee('01');
        $supervisorOne = $this->employee('101');
        $supervisorTwo = $this->employee('102');
        $technicianSlot = $this->slot('technician', 1);
        $supervisorSlotOne = $this->slot('supervisor', 1);
        $supervisorSlotTwo = $this->slot('supervisor', 2);

        $technicianSlot->update(['employee_id' => $technician->id]);
        $supervisorSlotOne->update(['employee_id' => $supervisorOne->id]);
        $supervisorSlotTwo->update(['employee_id' => $supervisorTwo->id]);

        $payload = $this->slotPayload('supervisor', 'employee_id');
        $payload['slots'][$supervisorSlotOne->id]['employee_id'] = $supervisorTwo->id;
        $payload['slots'][$supervisorSlotTwo->id]['employee_id'] = $supervisorOne->id;

        $this->actingAs($admin)
            ->put(route('safety.lototo.settings.update', 'supervisor'), $payload)
            ->assertRedirect(route('safety.lototo.settings'));

        $this->assertDatabaseHas('lototo_board_slots', [
            'id' => $supervisorSlotOne->id,
            'employee_id' => $supervisorTwo->id,
        ]);
        $this->assertDatabaseHas('lototo_board_slots', [
            'id' => $supervisorSlotTwo->id,
            'employee_id' => $supervisorOne->id,
        ]);
        $this->assertDatabaseHas('lototo_board_slots', [
            'id' => $technicianSlot->id,
            'employee_id' => $technician->id,
        ]);
    }

    public function test_empty_slot_and_new_position_do_not_shift_existing_positions(): void
    {
        $admin = $this->administrator();
        $technicianInSlot50 = $this->employee('50');
        $technicianInSlot51 = $this->employee('51');
        $slot50 = $this->slot('technician', 50);
        $slot51 = $this->slot('technician', 51);
        $slot50->update(['employee_id' => $technicianInSlot50->id]);
        $slot51->update(['employee_id' => $technicianInSlot51->id]);
        $history = $this->activity($technicianInSlot50);
        $history->update(['status' => 'completed', 'outcome' => 'finish', 'ended_at' => now()]);

        $payload = $this->slotPayload('technician', 'employee_id');
        $payload['slots'][$slot50->id]['employee_id'] = '';

        $this->actingAs($admin)
            ->put(route('safety.lototo.settings.update', 'technician'), $payload)
            ->assertRedirect(route('safety.lototo.settings'));

        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot50->id, 'employee_id' => null]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot51->id, 'employee_id' => $technicianInSlot51->id]);
        $this->assertDatabaseHas('employees', ['id' => $technicianInSlot50->id]);
        $this->assertDatabaseHas('lototo_activities', ['id' => $history->id, 'employee_id' => $technicianInSlot50->id]);

        $this->actingAs($admin)
            ->post(route('safety.lototo.settings.positions.store', 'technician'))
            ->assertRedirect(route('safety.lototo.settings'));

        $this->assertDatabaseHas('lototo_board_slots', ['category' => 'technician', 'position' => 66]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot51->id, 'position' => 51]);

        $this->get(route('safety.lototo.index'))
            ->assertOk()
            ->assertSee('LOTOTO 50', false)
            ->assertSee('LOTOTO 51', false)
            ->assertSee('>66</span>', false)
            ->assertSee('grid-column: 6; grid-row: 4;', false);
    }

    public function test_board_has_indexed_supervisor_slots_and_fifteen_other_items_in_one_row(): void
    {
        $admin = $this->administrator();

        $response = $this->actingAs($admin)->get(route('safety.lototo.index'));

        $response->assertOk()
            ->assertSee('SUPERVISOR posisi 10 kosong', false)
            ->assertSee('SUPERVISOR', false)
            ->assertSee('Tool 15')
            ->assertSee('lototo-technician-grid', false)
            ->assertSee('lototo-supervisor-grid', false)
            ->assertSee('grid-template-columns: repeat(20, minmax(0, 1fr))', false)
            ->assertSee('grid-template-columns: 2rem repeat(20, minmax(0, 1fr))', false)
            ->assertSee('grid-template-columns: 2rem repeat(10, minmax(0, 1fr))', false)
            ->assertSee('grid-column: 11 / span 11', false)
            ->assertSee('bg-yellow-400', false)
            ->assertSee('TECHNICIAN')
            ->assertSee('SUPERVISOR')
            ->assertSee('grid-column: 10; grid-row: 1;', false)
            ->assertSee('grid-column: 1; grid-row: 1;', false)
            ->assertSee('>01</span>', false)
            ->assertSee('>10</span>', false)
            ->assertSee('>15</span>', false)
            ->assertSee('grid-column: 1 / span 2;', false)
            ->assertSee('grid-column: 20 / span 1;', false);
        $this->assertSame(15, substr_count($response->getContent(), '>Tool '));
        $this->assertMatchesRegularExpression(
            '/lototo-supervisor-group.*?grid-row: 4 \/ span 1;.*?lototo-supervisor-grid.*?grid-column: 10; grid-row: 1;.*?SUPERVISOR posisi 1 kosong/s',
            $response->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/lototo-supervisor-grid.*?grid-column: 1; grid-row: 1;.*?SUPERVISOR posisi 10 kosong/s',
            $response->getContent(),
        );
    }

    public function test_board_keeps_fifteen_other_slots_visible_when_no_tool_slots_are_saved(): void
    {
        $admin = $this->administrator();
        LototoBoardSlot::query()->where('category', 'tool')->delete();

        $response = $this->actingAs($admin)->get(route('safety.lototo.index'));

        $response->assertOk()
            ->assertSee('Other posisi 1 kosong', false)
            ->assertSee('Other posisi 15 kosong', false)
            ->assertSee('grid-column: 1 / span 2;', false)
            ->assertSee('grid-column: 20 / span 1;', false);
        $this->assertSame(15, substr_count($response->getContent(), 'aria-label="Other posisi '));
        $this->assertMatchesRegularExpression(
            '/aria-label="Other posisi 1 kosong".*?<svg class="h-4 w-4 shrink-0 text-stone-500"/s',
            $response->getContent(),
        );
    }

    public function test_administrators_can_edit_and_add_persistent_tool_slots(): void
    {
        $admin = $this->administrator();
        $toolSlot = $this->slot('tool', 1);
        $payload = $this->slotPayload('tool', 'tool_label');
        $payload['slots'][$toolSlot->id]['tool_label'] = 'Impact Wrench';

        $this->actingAs($admin)
            ->put(route('safety.lototo.settings.update', 'tool'), $payload)
            ->assertRedirect(route('safety.lototo.settings'));

        $this->assertDatabaseHas('lototo_board_slots', [
            'id' => $toolSlot->id,
            'category' => 'tool',
            'position' => 1,
            'tool_label' => 'Impact Wrench',
        ]);

        $this->actingAs($admin)
            ->post(route('safety.lototo.settings.positions.store', 'tool'))
            ->assertRedirect(route('safety.lototo.settings'));

        $this->assertDatabaseHas('lototo_board_slots', [
            'category' => 'tool',
            'position' => 16,
            'tool_label' => null,
        ]);
        $this->get(route('safety.lototo.index'))
            ->assertOk()
            ->assertSee('Impact Wrench')
            ->assertSee('Other posisi 16 kosong', false)
            ->assertSee('grid-column: 1 / span 1; grid-row: 2;', false);
    }

    public function test_position_reordering_preserves_employee_assignments(): void
    {
        $admin = $this->administrator();
        $employeeOne = $this->employee('101');
        $employeeTwo = $this->employee('102');
        $slotOne = $this->slot('supervisor', 1);
        $slotTwo = $this->slot('supervisor', 2);
        $slotOne->update(['employee_id' => $employeeOne->id]);
        $slotTwo->update(['employee_id' => $employeeTwo->id]);
        $payload = $this->slotPayload('supervisor', 'employee_id');
        $payload['slots'][$slotOne->id]['position'] = 2;
        $payload['slots'][$slotTwo->id]['position'] = 1;

        $this->actingAs($admin)
            ->put(route('safety.lototo.settings.update', 'supervisor'), $payload)
            ->assertRedirect(route('safety.lototo.settings'));

        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slotOne->id, 'position' => 2, 'employee_id' => $employeeOne->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slotTwo->id, 'position' => 1, 'employee_id' => $employeeTwo->id]);
    }

    public function test_technician_position_changes_are_rejected_until_legacy_mapping_is_verified(): void
    {
        $admin = $this->administrator();
        $employee = $this->employee('01');
        $slot = $this->slot('technician', 1);
        $slot->update(['employee_id' => $employee->id]);
        $payload = $this->slotPayload('technician', 'employee_id');
        $payload['slots'][$slot->id]['position'] = 100;

        $this->actingAs($admin)
            ->put(route('safety.lototo.settings.update', 'technician'), $payload)
            ->assertSessionHasErrors('slots');

        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'position' => 1, 'employee_id' => $employee->id]);
    }

    public function test_new_slot_can_be_created_at_an_explicit_unused_position(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post(route('safety.lototo.settings.positions.store', 'technician'), ['position' => 100])
            ->assertRedirect(route('safety.lototo.settings'));

        $this->assertDatabaseHas('lototo_board_slots', ['category' => 'technician', 'position' => 100]);
        $this->actingAs($admin)
            ->post(route('safety.lototo.settings.positions.store', 'technician'), ['position' => 100])
            ->assertSessionHasErrors('position');
    }

    public function test_duplicate_positions_are_rejected_without_partial_updates(): void
    {
        $admin = $this->administrator();
        $slotOne = $this->slot('technician', 1);
        $slotTwo = $this->slot('technician', 2);
        $payload = $this->slotPayload('technician', 'employee_id');
        $payload['slots'][$slotTwo->id]['position'] = 1;

        $this->actingAs($admin)
            ->put(route('safety.lototo.settings.update', 'technician'), $payload)
            ->assertSessionHasErrors('slots');

        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slotOne->id, 'position' => 1]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slotTwo->id, 'position' => 2]);
    }

    public function test_only_empty_slots_can_be_deleted_and_other_positions_do_not_move(): void
    {
        $admin = $this->administrator();
        $employee = $this->employee('01');
        $assigned = $this->slot('technician', 1);
        $empty = $this->slot('supervisor', 1);
        $assigned->update(['employee_id' => $employee->id]);

        $this->actingAs($admin)
            ->delete(route('safety.lototo.settings.positions.destroy', ['technician', $assigned]))
            ->assertSessionHasErrors('slot');
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $assigned->id, 'employee_id' => $employee->id]);

        $this->actingAs($admin)
            ->delete(route('safety.lototo.settings.positions.destroy', ['supervisor', $empty]))
            ->assertRedirect(route('safety.lototo.settings'));
        $this->assertDatabaseMissing('lototo_board_slots', ['id' => $empty->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $assigned->id, 'position' => 1]);
    }

    public function test_non_admin_cannot_open_or_submit_spare_in_out_forms(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $spareLock = SpareLock::query()->where('code', 'SP-01')->firstOrFail();

        $this->actingAs($staff)
            ->get(route('safety.lototo.form', ['spare_lock' => $spareLock->code]))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('safety.lototo.spare-locks.show', $spareLock))
            ->assertOk()
            ->assertDontSee('Form IN / Mulai')
            ->assertDontSee('Form OUT / CLOSE');

        $this->actingAs($staff)
            ->post(route('safety.lototo.form.store'), [
                'lock_mode' => 'spare',
                'lock_status' => 'in',
            ])
            ->assertForbidden();
    }

    public function test_employee_with_active_activity_cannot_be_unassigned_from_a_slot(): void
    {
        $admin = $this->administrator();
        $employee = $this->employee('07');
        $slot = $this->slot('technician', 1);
        $slot->update(['employee_id' => $employee->id]);
        $activity = $this->activity($employee);

        $payload = $this->slotPayload('technician', 'employee_id');
        $payload['slots'][$slot->id]['employee_id'] = '';

        $this->actingAs($admin)
            ->put(route('safety.lototo.settings.update', 'technician'), $payload)
            ->assertSessionHasErrors('slots');

        $this->assertDatabaseHas('lototo_board_slots', [
            'id' => $slot->id,
            'employee_id' => $employee->id,
        ]);
        $this->assertDatabaseHas('lototo_activities', ['id' => $activity->id, 'status' => 'in_progress']);
    }

    public function test_spare_lock_with_active_activity_cannot_be_unassigned_from_a_slot(): void
    {
        $admin = $this->administrator();
        $employee = $this->employee('07');
        $spareLock = SpareLock::query()->where('code', 'SP-01')->firstOrFail();
        $slot = $this->slot('spare', 1);
        $activity = $this->activity($employee);
        $activity->update(['spare_lock_id' => $spareLock->id]);
        $spareLock->update(['current_activity_id' => $activity->id]);

        $payload = $this->slotPayload('spare', 'spare_lock_id');
        $payload['slots'][$slot->id]['spare_lock_id'] = '';

        $this->actingAs($admin)
            ->put(route('safety.lototo.settings.update', 'spare'), $payload)
            ->assertSessionHasErrors('slots');

        $this->assertDatabaseHas('lototo_board_slots', [
            'id' => $slot->id,
            'spare_lock_id' => $spareLock->id,
        ]);
        $this->assertDatabaseHas('lototo_activities', [
            'id' => $activity->id,
            'spare_lock_id' => $spareLock->id,
        ]);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);

        return $user;
    }

    private function employee(string $lototoNumber): Employee
    {
        return Employee::factory()->create([
            'business_unit_id' => $this->businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => $lototoNumber,
        ]);
    }

    private function slot(string $category, int $position): LototoBoardSlot
    {
        return LototoBoardSlot::query()
            ->where('category', $category)
            ->where('position', $position)
            ->firstOrFail();
    }

    private function slotPayload(string $category, string $assignmentField): array
    {
        return [
            'slots' => LototoBoardSlot::query()
                ->where('category', $category)
                ->get()
                ->mapWithKeys(fn (LototoBoardSlot $slot) => [
                    $slot->id => [
                        $assignmentField => $slot->getAttribute($assignmentField) ?? '',
                        'is_enabled' => $slot->is_enabled ? '1' : '0',
                    ],
                ])
                ->all(),
        ];
    }

    private function activity(Employee $employee): LototoActivity
    {
        return LototoActivity::query()->create([
            'employee_id' => $employee->id,
            'board' => 'mtn_bpr',
            'location' => 'Test',
            'activity' => 'Test activity',
            'risks' => ['terjepit'],
            'risk_controls' => 'Test controls',
            'knows_procedure' => true,
            'follows_lototo' => true,
            'uses_ppe' => true,
            'safe_tools' => true,
            'safe_hands' => true,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }
}
