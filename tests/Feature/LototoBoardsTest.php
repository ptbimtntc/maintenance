<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LototoActivity;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\LototoTool;
use App\Models\LototoToolActivity;
use App\Models\SpareLock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LototoBoardsTest extends TestCase
{
    use RefreshDatabase;

    private EmploymentStatus $activeStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->activeStatus = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
    }

    public function test_admin_can_create_board_from_active_business_unit_with_isolated_template(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create(['name' => 'Unit Two']);
        Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '50',
        ]);
        Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '101',
        ]);

        $this->actingAs($admin)
            ->post(route('safety.lototo.boards.store'), [
                'division_name' => 'Production',
                'business_unit_id' => $businessUnit->id,
            ])
            ->assertRedirect(route('safety.lototo.boards.index'));

        $board = LototoBoard::query()->where('division_name', 'Production')->firstOrFail();
        $this->assertSame($businessUnit->id, $board->business_unit_id);
        $this->assertDatabaseCount('lototo_board_slots', 65 + 10 + 20 + 15 + 50 + 10 + 20 + 15);
        $this->assertDatabaseHas('lototo_board_slots', [
            'board_id' => $board->id,
            'category' => 'technician',
            'position' => 50,
            'employee_id' => Employee::query()->where('business_unit_id', $businessUnit->id)->where('lototo_number', '50')->value('id'),
        ]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'category' => 'technician', 'position' => 25, 'employee_id' => null]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'category' => 'supervisor', 'position' => 10]);
        $this->assertSame(20, LototoBoardSlot::query()->where('board_id', $board->id)->whereNotNull('spare_lock_id')->count());
        $this->assertSame(['SP-01', 'SP-20'], [
            SpareLock::query()->where('board_id', $board->id)->orderBy('code')->value('code'),
            SpareLock::query()->where('board_id', $board->id)->orderByDesc('code')->value('code'),
        ]);
        $this->assertSame(20, $board->layout['board_columns']);
        $this->assertLessThanOrEqual(30, strlen($board->code));
    }

    public function test_add_board_stores_the_selected_column_count(): void
    {
        $businessUnit = BusinessUnit::factory()->create();

        $this->actingAs($this->administrator())
            ->post(route('safety.lototo.boards.store'), [
                'division_name' => 'Half Product',
                'business_unit_id' => $businessUnit->id,
                'board_columns' => 15,
                'spare_lock_count' => 5,
            ])
            ->assertRedirect(route('safety.lototo.boards.index'));

        $board = LototoBoard::query()->where('division_name', 'Half Product')->firstOrFail();
        $this->assertSame(15, $board->layout['board_columns']);
        $this->assertSame(15, $board->layout['technician_columns']);
        $this->assertSame(15, $board->layout['supervisor_columns']);
        $this->assertSame(5, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'spare')->count());
        $this->actingAs($this->administrator())
            ->get(route('safety.lototo.boards.show', $board))
            ->assertOk()
            ->assertSee('--lototo-board-columns: 15;', false);
    }

    public function test_invalid_business_unit_and_duplicate_division_are_rejected(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create(['name' => 'Unit Two']);
        $payload = ['division_name' => 'Production', 'business_unit_id' => $businessUnit->id];

        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), [
            ...$payload,
            'business_unit_id' => 999999,
        ])->assertSessionHasErrors('business_unit_id');

        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), $payload)->assertSessionHasErrors('division_name');
    }

    public function test_non_admin_cannot_manage_boards(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->actingAs($staff)->get(route('safety.lototo.boards.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('safety.lototo.boards.store'), [
            'division_name' => 'Production',
            'business_unit_id' => BusinessUnit::factory()->create()->id,
        ])->assertForbidden();
        $board = $this->board(BusinessUnit::factory()->create());
        $this->actingAs($staff)->delete(route('safety.lototo.boards.destroy', $board))->assertForbidden();
    }

    public function test_edit_board_changes_presentation_without_touching_slot_or_activity_data(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $board = $this->board($businessUnit);
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '01',
        ]);
        $slot = LototoBoardSlot::query()->create([
            'board_id' => $board->id,
            'category' => 'technician',
            'position' => 1,
            'employee_id' => $employee->id,
            'is_enabled' => true,
        ]);
        $activity = LototoActivity::query()->create([
            'employee_id' => $employee->id,
            'board' => $board->code,
            'location' => 'Test',
            'activity' => 'Test work',
            'risks' => ['terjepit'],
            'risk_controls' => 'Isolate',
            'knows_procedure' => true,
            'follows_lototo' => true,
            'uses_ppe' => true,
            'safe_tools' => true,
            'safe_hands' => true,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('safety.lototo.boards.presentation.update', $board), [
            'title' => 'New title',
            'subtitle' => 'New subtitle',
            'section_order' => ['spare', 'other'],
            'components' => ['technician' => '1', 'supervisor' => '1', 'other' => '1', 'spare' => '1'],
        ])->assertRedirect(route('safety.lototo.boards.edit', $board));

        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id, 'title' => 'New title', 'subtitle' => 'New subtitle']);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'employee_id' => $employee->id, 'position' => 1]);
        $this->assertDatabaseHas('lototo_activities', ['id' => $activity->id, 'status' => 'in_progress', 'board' => $board->code]);
    }

    public function test_board_with_active_work_can_be_deactivated_without_ending_work_or_moving_units(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $otherUnit = BusinessUnit::factory()->create();
        $board = $this->board($businessUnit);
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '01',
        ]);
        $supervisor = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '101',
        ]);
        $spareLock = SpareLock::query()->create([
            'board_id' => $board->id,
            'code' => 'SP-ACTIVE',
            'is_active' => true,
        ]);
        LototoBoardSlot::query()->create([
            'board_id' => $board->id,
            'category' => 'technician',
            'position' => 1,
            'employee_id' => $employee->id,
            'is_enabled' => true,
        ]);
        LototoBoardSlot::query()->create([
            'board_id' => $board->id,
            'category' => 'supervisor',
            'position' => 1,
            'employee_id' => $supervisor->id,
            'is_enabled' => true,
        ]);
        LototoBoardSlot::query()->create([
            'board_id' => $board->id,
            'category' => 'spare',
            'position' => 1,
            'spare_lock_id' => $spareLock->id,
            'is_enabled' => true,
        ]);
        LototoBoardSlot::query()->create([
            'board_id' => $board->id,
            'category' => 'tool',
            'position' => 1,
            'tool_label' => 'Torque wrench',
            'is_enabled' => true,
        ]);
        LototoActivity::query()->create([
            'employee_id' => $employee->id,
            'board' => $board->code,
            'location' => 'Test',
            'activity' => 'Test work',
            'risks' => ['terjepit'],
            'risk_controls' => 'Isolate',
            'knows_procedure' => true,
            'follows_lototo' => true,
            'uses_ppe' => true,
            'safe_tools' => true,
            'safe_hands' => true,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('safety.lototo.boards.update', $board), [
            'division_name' => $board->division_name,
            'business_unit_id' => $businessUnit->id,
            'is_active' => '0',
        ])->assertSessionHas('status');
        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id, 'is_active' => false]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'employee_id' => $employee->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'employee_id' => $supervisor->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'spare_lock_id' => $spareLock->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'tool_label' => 'Torque wrench']);
        $this->assertDatabaseHas('spare_locks', ['id' => $spareLock->id, 'board_id' => $board->id]);
        $this->assertDatabaseHas('lototo_activities', ['board' => $board->code, 'status' => 'in_progress']);

        $this->actingAs($admin)->put(route('safety.lototo.boards.update', $board), [
            'division_name' => $board->division_name,
            'business_unit_id' => $otherUnit->id,
            'is_active' => '0',
        ])->assertSessionHasErrors('business_unit_id');
    }

    public function test_admin_can_reactivate_board_without_duplicating_or_changing_configuration(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $board = $this->board($businessUnit);
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '01',
        ]);
        $slot = LototoBoardSlot::query()->create([
            'board_id' => $board->id,
            'category' => 'technician',
            'position' => 1,
            'employee_id' => $employee->id,
            'is_enabled' => true,
        ]);
        $board->update(['is_active' => false]);

        $this->actingAs($admin)->put(route('safety.lototo.boards.update', $board), [
            'division_name' => $board->division_name,
            'business_unit_id' => $businessUnit->id,
            'is_active' => '1',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id, 'is_active' => true]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'employee_id' => $employee->id]);
        $this->assertSame(1, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'technician')->count());
    }

    public function test_inactive_board_rejects_new_employee_assignment(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $board = $this->board($businessUnit);
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '01',
        ]);
        $slot = LototoBoardSlot::query()->create([
            'board_id' => $board->id,
            'category' => 'technician',
            'position' => 1,
            'is_enabled' => true,
        ]);
        $board->update(['is_active' => false]);

        $this->actingAs($admin)->put(route('safety.lototo.settings.update', 'technician'), [
            'board_id' => $board->id,
            'slots' => [$slot->id => ['employee_id' => $employee->id, 'is_enabled' => '1', 'position' => '1']],
        ])->assertSessionHasErrors('slots');

        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'employee_id' => null]);
    }

    public function test_inactive_board_rejects_new_in_but_allows_existing_activity_finish(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $board = $this->board($businessUnit);
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '01',
        ]);
        $activity = LototoActivity::query()->create([
            'employee_id' => $employee->id,
            'board' => $board->code,
            'location' => 'Test',
            'activity' => 'Test work',
            'risks' => ['terjepit'],
            'risk_controls' => 'Isolate',
            'knows_procedure' => true,
            'follows_lototo' => true,
            'uses_ppe' => true,
            'safe_tools' => true,
            'safe_hands' => true,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
        $spareLock = SpareLock::query()->create([
            'board_id' => $board->id,
            'code' => 'SP-OUT',
            'is_active' => true,
        ]);
        $board->update(['is_active' => false]);

        $this->actingAs($admin)->post(route('safety.lototo.form.store'), [
            'board_id' => $board->id,
            'employee_id' => $employee->id,
            'lototo_number' => $employee->lototo_number,
            'lock_mode' => 'personal',
            'lock_status' => 'in',
            'location' => 'New',
            'activity' => 'New work',
            'risks' => ['terjepit'],
            'risk_controls' => 'Isolate',
            'knows_procedure' => 'ya',
            'follows_lototo' => 'ya',
            'uses_ppe' => 'ya',
            'safe_tools' => 'ya',
            'safe_hands' => 'ya',
        ])->assertSessionHasErrors('lock_status');

        $this->actingAs($admin)->post(route('safety.lototo.form.store'), [
            'board_id' => $board->id,
            'employee_id' => $employee->id,
            'lototo_number' => $employee->lototo_number,
            'lock_mode' => 'personal',
            'lock_status' => 'out',
            'outcome' => 'outstanding',
            'spare_lock_id' => $spareLock->id,
        ])->assertSessionHasErrors('lock_status');

        $this->actingAs($admin)->post(route('safety.lototo.form.store'), [
            'board_id' => $board->id,
            'employee_id' => $employee->id,
            'lototo_number' => $employee->lototo_number,
            'lock_mode' => 'personal',
            'lock_status' => 'out',
            'outcome' => 'finish',
        ])->assertRedirect();

        $this->assertDatabaseHas('lototo_activities', ['id' => $activity->id, 'status' => 'completed', 'outcome' => 'finish']);
    }

    public function test_admin_can_delete_inactive_empty_board_and_its_blank_slot_configuration(): void
    {
        $admin = $this->administrator();
        $board = $this->board(BusinessUnit::factory()->create());
        $board->update(['is_active' => false]);
        LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'technician', 'position' => 1, 'is_enabled' => true]);
        LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'tool', 'position' => 1, 'is_enabled' => true]);

        $this->actingAs($admin)->delete(route('safety.lototo.boards.destroy', $board))
            ->assertRedirect(route('safety.lototo.boards.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('lototo_boards', ['id' => $board->id]);
        $this->assertDatabaseMissing('lototo_board_slots', ['board_id' => $board->id]);
    }

    public function test_board_with_history_cannot_be_deleted_and_history_is_preserved(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $board = $this->board($businessUnit);
        $board->update(['is_active' => false]);
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '01',
        ]);
        $activity = LototoActivity::query()->create([
            'employee_id' => $employee->id,
            'board' => $board->code,
            'location' => 'Test',
            'activity' => 'Test work',
            'risks' => ['terjepit'],
            'risk_controls' => 'Isolate',
            'knows_procedure' => true,
            'follows_lototo' => true,
            'uses_ppe' => true,
            'safe_tools' => true,
            'safe_hands' => true,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('safety.lototo.boards.destroy', $board))
            ->assertSessionHasErrors('board');
        $this->assertDatabaseHas('lototo_activities', ['id' => $activity->id, 'board' => $board->code]);
        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id]);
    }

    public function test_board_with_spare_lock_cannot_be_deleted(): void
    {
        $admin = $this->administrator();
        $board = $this->board(BusinessUnit::factory()->create());
        $board->update(['is_active' => false]);
        $spareLock = SpareLock::query()->create([
            'board_id' => $board->id,
            'code' => 'SP-TEST',
            'is_active' => false,
        ]);
        $slot = LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'spare', 'position' => 1, 'spare_lock_id' => $spareLock->id, 'is_enabled' => true]);

        $this->actingAs($admin)->delete(route('safety.lototo.boards.destroy', $board))
            ->assertSessionHasErrors('board');

        $this->assertDatabaseHas('spare_locks', ['id' => $spareLock->id, 'board_id' => $board->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'spare_lock_id' => $spareLock->id]);
        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id]);
    }

    public function test_board_with_tools_cannot_be_deleted(): void
    {
        $admin = $this->administrator();
        $board = $this->board(BusinessUnit::factory()->create());
        $board->update(['is_active' => false]);
        $tool = LototoTool::query()->create(['board_id' => $board->id, 'code' => 'TL-01', 'name' => 'Kunci']);
        $slot = LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'tool', 'position' => 1, 'tool_label' => 'Kunci', 'tool_id' => $tool->id, 'is_enabled' => true]);

        $this->actingAs($admin)->delete(route('safety.lototo.boards.destroy', $board))
            ->assertSessionHasErrors('board');

        $this->assertDatabaseHas('lototo_tools', ['id' => $tool->id, 'board_id' => $board->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'tool_id' => $tool->id]);
        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id]);
    }

    public function test_add_board_creates_physical_spare_locks_with_the_chosen_prefix(): void
    {
        $businessUnit = BusinessUnit::factory()->create(['name' => 'Half Product']);

        $this->actingAs($this->administrator())
            ->post(route('safety.lototo.boards.store'), [
                'division_name' => 'Maintenance',
                'business_unit_id' => $businessUnit->id,
                'spare_lock_count' => 3,
                'spare_code_prefix' => 'sph',
            ])
            ->assertSessionHasNoErrors();

        $board = LototoBoard::query()->where('business_unit_id', $businessUnit->id)->firstOrFail();
        $locks = SpareLock::query()->where('board_id', $board->id)->orderBy('code')->get();
        $this->assertSame(['SPH-01', 'SPH-02', 'SPH-03'], $locks->pluck('code')->all());
        $this->assertTrue($locks->every->is_active);
        foreach ($locks as $index => $lock) {
            $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'category' => 'spare', 'position' => $index + 1, 'spare_lock_id' => $lock->id]);
        }
        $this->assertSame(3, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'spare')->count());

        // The QR of each lock identifies this lock on this board.
        $this->actingAs($this->administrator())
            ->get(route('safety.lototo.settings', ['board' => $board->id]))
            ->assertOk()
            ->assertSee(e(route('safety.lototo.form', ['spare_lock' => 'SPH-02', 'board' => $board->id])), false);
        $this->actingAs($this->administrator())
            ->get(route('safety.lototo.form', ['spare_lock' => 'SPH-02', 'board' => $board->id]))
            ->assertOk()
            ->assertSee('name="board_id" value="'.$board->id.'"', false)
            ->assertSee('name="spare_lock_id" value="'.$locks[1]->id.'"', false);
    }

    public function test_add_board_rejects_invalid_spare_count_and_prefix(): void
    {
        $businessUnit = BusinessUnit::factory()->create();
        $payload = ['division_name' => 'Maintenance', 'business_unit_id' => $businessUnit->id];

        foreach ([['spare_lock_count' => -1], ['spare_lock_count' => 2.5], ['spare_lock_count' => 201], ['spare_code_prefix' => 'SP 01'], ['spare_code_prefix' => 'SP-']] as $invalid) {
            $this->actingAs($this->administrator())
                ->post(route('safety.lototo.boards.store'), [...$payload, ...$invalid])
                ->assertSessionHasErrors(array_key_first($invalid));
        }
        $this->assertDatabaseCount('lototo_boards', 0);
        $this->assertDatabaseCount('spare_locks', 0);
    }

    public function test_resaving_a_board_never_duplicates_spare_locks_and_growing_continues_the_series(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $board = $this->boardWithSpareLocks($businessUnit, 3, 'SPH');
        $originalIds = SpareLock::query()->where('board_id', $board->id)->orderBy('id')->pluck('id')->all();

        $this->updateBoard($admin, $board, ['spare_lock_count' => 3])->assertSessionHasNoErrors();
        $this->updateBoard($admin, $board, ['spare_lock_count' => 3])->assertSessionHasNoErrors();
        $this->assertSame($originalIds, SpareLock::query()->where('board_id', $board->id)->orderBy('id')->pluck('id')->all());

        $this->updateBoard($admin, $board, ['spare_lock_count' => 5])->assertSessionHasNoErrors();
        $this->assertSame(['SPH-01', 'SPH-02', 'SPH-03', 'SPH-04', 'SPH-05'], SpareLock::query()->where('board_id', $board->id)->orderBy('code')->pluck('code')->all());
        $this->assertSame($originalIds, array_slice(SpareLock::query()->where('board_id', $board->id)->orderBy('id')->pluck('id')->all(), 0, 3));
        $this->assertSame(5, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'spare')->whereNotNull('spare_lock_id')->count());
    }

    public function test_reducing_spare_count_keeps_used_locks_and_their_history(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $board = $this->boardWithSpareLocks($businessUnit, 3, 'SP');
        $usedLock = SpareLock::query()->where('board_id', $board->id)->where('code', 'SP-03')->firstOrFail();
        $activity = $this->spareActivity($board, $usedLock, 'completed');

        $this->updateBoard($admin, $board, ['spare_lock_count' => 1])->assertSessionHasErrors('spare_lock_count');
        $this->assertStringContainsString('gembok SP-03 sedang digunakan atau memiliki riwayat', session('errors')->first('spare_lock_count'));
        $this->assertSame(3, SpareLock::query()->where('board_id', $board->id)->count());
        $this->assertSame(3, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'spare')->count());
        $this->assertDatabaseHas('lototo_activities', ['id' => $activity->id, 'spare_lock_id' => $usedLock->id]);

        $otherBoard = $this->boardWithSpareLocks(BusinessUnit::factory()->create(['name' => 'Other Unit']), 3, 'SP');
        $this->updateBoard($admin, $otherBoard, ['spare_lock_count' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['SP-01'], SpareLock::query()->where('board_id', $otherBoard->id)->pluck('code')->all());
        $this->assertSame(1, LototoBoardSlot::query()->where('board_id', $otherBoard->id)->where('category', 'spare')->count());
        $this->assertSame(3, SpareLock::query()->where('board_id', $board->id)->count());
    }

    public function test_inactive_board_cannot_receive_new_spare_locks(): void
    {
        $board = $this->boardWithSpareLocks(BusinessUnit::factory()->create(), 1, 'SP');
        $board->update(['is_active' => false]);

        $this->updateBoard($this->administrator(), $board, ['spare_lock_count' => 2, 'is_active' => 0])
            ->assertSessionHasErrors('spare_lock_count');
        $this->assertSame(1, SpareLock::query()->where('board_id', $board->id)->count());
    }

    public function test_spare_code_can_be_saved_without_changing_its_status(): void
    {
        $admin = $this->administrator();
        $board = $this->boardWithSpareLocks(BusinessUnit::factory()->create(), 1, 'SPH');
        $lock = SpareLock::query()->where('board_id', $board->id)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('safety.lototo.spare-locks.update', $lock), ['board_id' => $board->id, 'code' => 'sph-10', 'is_active' => 1])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'cetak ulang QR SPH-10'));

        $this->assertDatabaseHas('spare_locks', ['id' => $lock->id, 'code' => 'SPH-10', 'is_active' => true]);
        $this->actingAs($admin)
            ->get(route('safety.lototo.form', ['spare_lock' => 'SPH-10', 'board' => $board->id]))
            ->assertOk()
            ->assertSee('name="spare_lock_id" value="'.$lock->id.'"', false);
        $this->actingAs($admin)
            ->get(route('safety.lototo.form', ['spare_lock' => 'SPH-01', 'board' => $board->id]))
            ->assertNotFound();
    }

    public function test_unused_spare_lock_can_be_deleted_but_used_or_foreign_locks_cannot(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $board = $this->boardWithSpareLocks($businessUnit, 3, 'SP');
        [$unused, $withHistory, $inUse] = SpareLock::query()->where('board_id', $board->id)->orderBy('code')->get()->all();
        $history = $this->spareActivity($board, $withHistory, 'completed');
        $running = $this->spareActivity($board, $inUse, 'in_progress');
        $inUse->update(['current_activity_id' => $running->id]);
        $otherBoard = $this->boardWithSpareLocks(BusinessUnit::factory()->create(['name' => 'Other Unit']), 1, 'SP');
        $foreignLock = SpareLock::query()->where('board_id', $otherBoard->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('safety.lototo.spare-locks.destroy', $unused), ['board_id' => $board->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('spare_locks', ['id' => $unused->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'category' => 'spare', 'position' => 1, 'spare_lock_id' => null]);

        foreach ([$withHistory, $inUse] as $lock) {
            $this->actingAs($admin)->delete(route('safety.lototo.spare-locks.destroy', $lock), ['board_id' => $board->id])
                ->assertSessionHasErrors('spare_lock');
            $this->assertDatabaseHas('spare_locks', ['id' => $lock->id]);
        }
        $this->assertDatabaseHas('lototo_activities', ['id' => $history->id, 'spare_lock_id' => $withHistory->id]);
        $this->assertDatabaseHas('lototo_activities', ['id' => $running->id, 'spare_lock_id' => $inUse->id]);

        // A lock of another board cannot be deleted through this board.
        $this->actingAs($admin)->delete(route('safety.lototo.spare-locks.destroy', $foreignLock), ['board_id' => $board->id])
            ->assertNotFound();
        $this->assertDatabaseHas('spare_locks', ['id' => $foreignLock->id]);

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->actingAs($staff)->delete(route('safety.lototo.spare-locks.destroy', $foreignLock), ['board_id' => $otherBoard->id])
            ->assertForbidden();
    }

    public function test_spare_settings_page_offers_delete_only_for_unused_locks(): void
    {
        $board = $this->boardWithSpareLocks(BusinessUnit::factory()->create(), 2, 'SP');
        $withHistory = SpareLock::query()->where('board_id', $board->id)->where('code', 'SP-02')->firstOrFail();
        $this->spareActivity($board, $withHistory, 'completed');
        $unused = SpareLock::query()->where('board_id', $board->id)->where('code', 'SP-01')->firstOrFail();

        $this->actingAs($this->administrator())
            ->get(route('safety.lototo.settings', ['board' => $board->id, 'section' => 'spare']))
            ->assertOk()
            ->assertSee('data-confirm-spare-delete data-spare-code="'.$unused->code.'"', false)
            ->assertDontSee('data-spare-code="'.$withHistory->code.'"', false)
            ->assertSee('Memiliki riwayat')
            ->assertSee('Simpan kode')
            ->assertSee('cetak ulang QR');
    }

    public function test_deleting_a_board_removes_its_slot_assignments_without_touching_other_boards(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '01',
        ]);
        $board = $this->board($businessUnit);
        $board->update(['is_active' => false]);
        LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'technician', 'position' => 1, 'employee_id' => $employee->id, 'is_enabled' => true]);
        LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'tool', 'position' => 1, 'tool_label' => 'Kunci', 'is_enabled' => true]);
        $otherBoard = $this->board(BusinessUnit::factory()->create(['name' => 'Other Unit']));
        $otherSlot = LototoBoardSlot::query()->create(['board_id' => $otherBoard->id, 'category' => 'technician', 'position' => 1, 'is_enabled' => true]);

        $this->actingAs($admin)->delete(route('safety.lototo.boards.destroy', $board))
            ->assertRedirect(route('safety.lototo.boards.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('lototo_boards', ['id' => $board->id]);
        $this->assertDatabaseMissing('lototo_board_slots', ['board_id' => $board->id]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('lototo_boards', ['id' => $otherBoard->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $otherSlot->id, 'board_id' => $otherBoard->id]);
    }

    public function test_each_board_can_register_its_own_spare_lock_code(): void
    {
        $admin = $this->administrator();
        $firstBoard = $this->board(BusinessUnit::factory()->create());
        $secondBoard = $this->board(BusinessUnit::factory()->create(['name' => 'Half Product']));

        foreach ([$firstBoard, $secondBoard] as $board) {
            $this->actingAs($admin)
                ->post(route('safety.lototo.spare-locks.store'), ['code' => 'SP-05', 'board_id' => $board->id])
                ->assertSessionHasNoErrors();
        }
        $this->actingAs($admin)
            ->post(route('safety.lototo.spare-locks.store'), ['code' => 'SP-05', 'board_id' => $secondBoard->id])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, SpareLock::query()->where('board_id', $firstBoard->id)->where('code', 'SP-05')->count());
        $this->assertSame(1, SpareLock::query()->where('board_id', $secondBoard->id)->where('code', 'SP-05')->count());
        $this->actingAs($admin)
            ->get(route('safety.lototo.form', ['spare_lock' => 'SP-05', 'board' => $secondBoard->id]))
            ->assertOk()
            ->assertSee('name="board_id" value="'.$secondBoard->id.'"', false);
    }

    public function test_active_board_cannot_be_deleted(): void
    {
        $board = $this->board(BusinessUnit::factory()->create());

        $this->actingAs($this->administrator())->delete(route('safety.lototo.boards.destroy', $board))
            ->assertSessionHasErrors('board');

        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id]);
    }

    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        return $admin;
    }

    public function test_changing_board_columns_repeatedly_keeps_slots_locks_and_other_boards(): void
    {
        $admin = $this->administrator();
        $board = $this->boardWithSpareLocks(BusinessUnit::factory()->create(), 3, 'SP');
        LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'tool', 'position' => 1, 'tool_label' => 'Kunci', 'tool_column_span' => 2, 'is_enabled' => true]);
        $otherBoard = $this->boardWithSpareLocks(BusinessUnit::factory()->create(['name' => 'Other Unit']), 2, 'SPX');
        $otherLayout = $otherBoard->fresh()->layout;
        $snapshot = fn () => LototoBoardSlot::query()->orderBy('id')->get(['id', 'board_id', 'category', 'position', 'spare_lock_id', 'tool_label', 'tool_column_span'])->toArray();
        $before = [$snapshot(), SpareLock::query()->orderBy('id')->get(['id', 'board_id', 'code'])->toArray()];

        foreach ([10, 15, 20, 10] as $columns) {
            $this->actingAs($admin)->put(route('safety.lototo.boards.presentation.update', $board), [
                'title' => 'Board',
                'subtitle' => 'Sub',
                'section_order' => ['spare', 'other'],
                'board_columns' => $columns,
                'components' => ['technician' => 1, 'supervisor' => 1, 'other' => 1, 'spare' => 1],
            ])->assertSessionHasNoErrors();
            $layout = $board->fresh()->layout;
            $this->assertSame([$columns, $columns, $columns], [$layout['board_columns'], $layout['technician_columns'], $layout['supervisor_columns']]);
            $this->actingAs($admin)->get(route('safety.lototo.boards.show', $board))->assertOk()->assertSee('--lototo-board-columns: '.$columns.';', false);
        }

        $this->actingAs($admin)->put(route('safety.lototo.boards.presentation.update', $board), [
            'title' => 'Board', 'subtitle' => 'Sub', 'section_order' => ['spare', 'other'], 'board_columns' => 12,
            'components' => ['technician' => 1, 'supervisor' => 1, 'other' => 1, 'spare' => 1],
        ])->assertSessionHasErrors('board_columns');
        $this->assertSame(10, $board->fresh()->layout['board_columns']);

        $this->assertSame($before, [$snapshot(), SpareLock::query()->orderBy('id')->get(['id', 'board_id', 'code'])->toArray()]);
        $this->assertSame($otherLayout, $otherBoard->fresh()->layout);
    }

    public function test_tool_column_size_is_saved_for_the_selected_board_only(): void
    {
        $admin = $this->administrator();
        $board = $this->board(BusinessUnit::factory()->create());
        $otherBoard = $this->board(BusinessUnit::factory()->create(['name' => 'Other Unit']));
        $tool = LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'tool', 'position' => 1, 'tool_label' => 'Kunci', 'is_enabled' => true]);
        $otherTool = LototoBoardSlot::query()->create(['board_id' => $otherBoard->id, 'category' => 'tool', 'position' => 1, 'tool_label' => 'Obeng', 'is_enabled' => true]);

        $this->actingAs($admin)->put(route('safety.lototo.settings.update', 'tool'), [
            'board_id' => $board->id,
            'slots' => [$tool->id => ['tool_label' => 'Kunci', 'tool_column_span' => 3, 'is_enabled' => 1, 'position' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lototo_board_slots', ['id' => $tool->id, 'tool_column_span' => 3, 'tool_label' => 'Kunci']);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $otherTool->id, 'tool_column_span' => 1, 'tool_label' => 'Obeng']);
        $this->actingAs($admin)->get(route('safety.lototo.boards.show', $board))->assertOk()->assertSee('grid-column: 1 / span 3;', false);
    }

    public function test_editing_tool_labels_keeps_tool_records_in_step_and_protects_used_tools(): void
    {
        $admin = $this->administrator();
        $board = $this->board(BusinessUnit::factory()->create());
        $otherBoard = $this->board(BusinessUnit::factory()->create(['name' => 'Other Unit']));
        $renamed = LototoTool::query()->create(['board_id' => $board->id, 'code' => 'TL-01', 'name' => 'Tool 1']);
        $cleared = LototoTool::query()->create(['board_id' => $board->id, 'code' => 'TL-02', 'name' => 'Tool 2']);
        $used = LototoTool::query()->create(['board_id' => $board->id, 'code' => 'TL-03', 'name' => 'Tool 3']);
        $foreign = LototoTool::query()->create(['board_id' => $otherBoard->id, 'code' => 'TL-01', 'name' => 'Tool 1']);
        $slots = collect([1 => $renamed, 2 => $cleared, 3 => $used, 4 => null])->map(fn ($tool, $position) => LototoBoardSlot::query()->create([
            'board_id' => $board->id, 'category' => 'tool', 'position' => $position, 'tool_label' => $tool?->name, 'tool_id' => $tool?->id, 'is_enabled' => true,
        ]));
        $foreignSlot = LototoBoardSlot::query()->create(['board_id' => $otherBoard->id, 'category' => 'tool', 'position' => 1, 'tool_label' => 'Tool 1', 'tool_id' => $foreign->id, 'is_enabled' => true]);
        $history = LototoToolActivity::query()->create(['tool_id' => $used->id, 'board_id' => $board->id, 'location' => 'Line A', 'status' => 'finished', 'started_by' => $admin->id, 'ended_by' => $admin->id, 'started_at' => now()->subHour(), 'ended_at' => now()]);
        $submit = fn (array $labels) => $this->actingAs($admin)->put(route('safety.lototo.settings.update', 'tool'), [
            'board_id' => $board->id,
            'slots' => $slots->mapWithKeys(fn ($slot, $position) => [$slot->id => ['tool_label' => $labels[$position], 'tool_column_span' => 1, 'is_enabled' => 1, 'position' => $position]])->all(),
        ]);

        // Clearing a used tool is refused as a whole; nothing changes.
        $submit([1 => 'Kunci Pas', 2 => '', 3 => '', 4 => 'Obeng'])->assertSessionHasErrors('slots');
        $this->assertDatabaseHas('lototo_tools', ['id' => $renamed->id, 'name' => 'Tool 1']);
        $this->assertDatabaseHas('lototo_tools', ['id' => $cleared->id]);
        $this->assertSame(4, LototoTool::query()->count());

        $submit([1 => 'Kunci Pas', 2 => '', 3 => 'Tool 3', 4 => 'Obeng'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lototo_tools', ['id' => $renamed->id, 'name' => 'Kunci Pas', 'code' => 'TL-01']);
        $this->assertDatabaseMissing('lototo_tools', ['id' => $cleared->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slots[2]->id, 'tool_id' => null, 'tool_label' => null]);
        $this->assertDatabaseHas('lototo_tools', ['id' => $used->id, 'name' => 'Tool 3']);
        $created = LototoTool::query()->where('board_id', $board->id)->where('name', 'Obeng')->firstOrFail();
        $this->assertSame('TL-04', $created->code);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slots[4]->id, 'tool_id' => $created->id, 'tool_label' => 'Obeng']);
        $this->assertDatabaseHas('lototo_tool_activities', ['id' => $history->id, 'tool_id' => $used->id]);

        // The other board keeps its own TL-01 untouched.
        $this->assertDatabaseHas('lototo_tools', ['id' => $foreign->id, 'board_id' => $otherBoard->id, 'name' => 'Tool 1']);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $foreignSlot->id, 'tool_id' => $foreign->id]);

        // A slot that holds a tool cannot be deleted as an "empty" slot.
        $this->actingAs($admin)->delete(route('safety.lototo.settings.positions.destroy', ['category' => 'tool', 'slot' => $slots[1]->id]), ['board_id' => $board->id])
            ->assertSessionHasErrors('slot');
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slots[1]->id]);
    }

    public function test_tool_history_is_protected_by_the_database(): void
    {
        $admin = $this->administrator();
        $board = $this->board(BusinessUnit::factory()->create());
        $tool = LototoTool::query()->create(['board_id' => $board->id, 'code' => 'TL-01', 'name' => 'Kunci']);
        $open = fn (string $location) => LototoToolActivity::query()->create(['tool_id' => $tool->id, 'board_id' => $board->id, 'location' => $location, 'status' => 'in_progress', 'started_by' => $admin->id, 'started_at' => now()]);

        $first = $open('Line A');
        $this->assertTrue($tool->openActivity()->is($first));
        try {
            $open('Line B');
            $this->fail('A second open IN for the same tool must be rejected.');
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
        }
        $first->update(['status' => 'finished', 'ended_by' => $admin->id, 'ended_at' => now()]);
        $this->assertNull($tool->openActivity()->first());
        $open('Line C');
        $this->assertSame(2, $tool->activities()->count());

        try {
            $tool->delete();
            $this->fail('A tool with history must not be deletable.');
        } catch (\Illuminate\Database\QueryException) {
        }
        $this->assertDatabaseHas('lototo_tools', ['id' => $tool->id]);
        $this->assertSame(2, LototoToolActivity::query()->where('tool_id', $tool->id)->count());
    }

    public function test_spare_settings_adds_several_locks_with_sequential_codes_and_positions(): void
    {
        $admin = $this->administrator();
        $board = $this->boardWithSpareLocks(BusinessUnit::factory()->create(), 3, 'SPH');
        // A manually added replacement lock already uses SPH-05 and has no position, so the series skips that code.
        SpareLock::query()->create(['board_id' => $board->id, 'code' => 'SPH-05', 'is_active' => true]);
        $otherBoard = $this->boardWithSpareLocks(BusinessUnit::factory()->create(['name' => 'Other Unit']), 2, 'SPH');
        $before = SpareLock::query()->orderBy('id')->get(['id', 'board_id', 'code', 'is_active'])->toArray();

        $this->actingAs($admin)->get(route('safety.lototo.settings', ['board' => $board->id, 'section' => 'spare']))
            ->assertOk()->assertSee('Tambah gembok Spare')->assertSee('name="spare_code_prefix" value="SPH"', false);

        $this->actingAs($admin)->post(route('safety.lototo.spare-locks.store-batch'), ['board_id' => $board->id, 'count' => 3])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('safety.lototo.settings', ['board' => $board->id, 'section' => 'spare']));

        $this->assertSame(['SPH-01', 'SPH-02', 'SPH-03', 'SPH-04', 'SPH-05', 'SPH-06', 'SPH-07'], SpareLock::query()->where('board_id', $board->id)->orderBy('code')->pluck('code')->all());
        $this->assertSame(
            [1 => 'SPH-01', 2 => 'SPH-02', 3 => 'SPH-03', 4 => 'SPH-04', 5 => 'SPH-06', 6 => 'SPH-07'],
            LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'spare')->with('spareLock')->orderBy('position')->get()
                ->mapWithKeys(fn (LototoBoardSlot $slot) => [$slot->position => $slot->spareLock->code])->all()
        );
        // Existing locks (this board's and the other board's identical codes) are untouched.
        $this->assertSame($before, SpareLock::query()->whereKey(array_column($before, 'id'))->orderBy('id')->get(['id', 'board_id', 'code', 'is_active'])->toArray());
        $this->assertSame(2, SpareLock::query()->where('board_id', $otherBoard->id)->count());

        // A new prefix starts its own series; the new locks open the spare form of this board through their QR code.
        $this->actingAs($admin)->post(route('safety.lototo.spare-locks.store-batch'), ['board_id' => $board->id, 'count' => 1, 'spare_code_prefix' => 'spx'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'category' => 'spare', 'position' => 7, 'spare_lock_id' => SpareLock::query()->where('board_id', $board->id)->where('code', 'SPX-01')->value('id')]);
        $this->actingAs($admin)->get(route('safety.lototo.form', ['spare_lock' => 'SPH-07', 'board' => $board->id]))
            ->assertOk()->assertSee('name="board_id" value="'.$board->id.'"', false);
    }

    public function test_spare_batch_is_validated_and_refused_for_inactive_boards_and_non_managers(): void
    {
        $admin = $this->administrator();
        $board = $this->boardWithSpareLocks(BusinessUnit::factory()->create(), 1, 'SP');

        foreach ([['count' => 0], ['count' => 51], ['count' => 2, 'spare_code_prefix' => 'S P']] as $invalid) {
            $this->actingAs($admin)->post(route('safety.lototo.spare-locks.store-batch'), ['board_id' => $board->id, ...$invalid])->assertSessionHasErrors();
        }
        $staff = User::factory()->create();
        $this->actingAs($staff)->post(route('safety.lototo.spare-locks.store-batch'), ['board_id' => $board->id, 'count' => 1])->assertForbidden();
        $board->update(['is_active' => false]);
        $this->actingAs($admin)->post(route('safety.lototo.spare-locks.store-batch'), ['board_id' => $board->id, 'count' => 1])->assertSessionHasErrors('count');

        $this->assertSame(['SP-01'], SpareLock::query()->where('board_id', $board->id)->pluck('code')->all());
        $this->assertSame(1, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'spare')->count());
    }

    private function boardWithSpareLocks(BusinessUnit $businessUnit, int $count, string $prefix): LototoBoard
    {
        $board = $this->board($businessUnit);
        foreach (range(1, $count) as $position) {
            $lock = SpareLock::query()->create(['board_id' => $board->id, 'code' => sprintf('%s-%02d', $prefix, $position), 'is_active' => true]);
            LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'spare', 'position' => $position, 'spare_lock_id' => $lock->id, 'is_enabled' => true]);
        }

        return $board;
    }

    private function updateBoard(User $admin, LototoBoard $board, array $changes): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->put(route('safety.lototo.boards.update', $board), [
            'division_name' => $board->division_name,
            'business_unit_id' => $board->business_unit_id,
            'is_active' => $board->is_active ? 1 : 0,
            ...$changes,
        ]);
    }

    private function spareActivity(LototoBoard $board, SpareLock $lock, string $status): LototoActivity
    {
        $employee = Employee::factory()->create([
            'business_unit_id' => $board->business_unit_id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => (string) random_int(10, 99),
        ]);

        return LototoActivity::query()->create([
            'employee_id' => $employee->id,
            'spare_lock_id' => $lock->id,
            'board' => $board->code,
            'location' => 'Test',
            'activity' => 'Test work',
            'risks' => ['terjepit'],
            'risk_controls' => 'Isolate',
            'knows_procedure' => true,
            'follows_lototo' => true,
            'uses_ppe' => true,
            'safe_tools' => true,
            'safe_hands' => true,
            'status' => $status,
            'started_at' => now()->subHour(),
            'ended_at' => $status === 'completed' ? now() : null,
        ]);
    }

    private function board(BusinessUnit $businessUnit): LototoBoard
    {
        return LototoBoard::query()->create([
            'business_unit_id' => $businessUnit->id,
            'division_name' => 'Maintenance',
            'code' => 'board-'.strtolower(str_replace(' ', '-', $businessUnit->name)).'-'.uniqid(),
            'title' => 'MAINTENANCE · '.$businessUnit->name,
            'subtitle' => 'Daftar LOTOTO karyawan dan spare',
            'layout' => ['technician_columns' => 20, 'supervisor_columns' => 10, 'section_order' => ['technician', 'other', 'spare']],
            'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true],
            'is_active' => true,
        ]);
    }
}
