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
                'spare_lock_count' => 20,
            ])
            ->assertRedirect(route('safety.lototo.boards.index'))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'SP-UT-01 s.d. SP-UT-20'));

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
        $this->assertSame(['SP-UT-01', 'SP-UT-20'], [
            SpareLock::query()->where('board_id', $board->id)->orderBy('code')->value('code'),
            SpareLock::query()->where('board_id', $board->id)->orderByDesc('code')->value('code'),
        ]);
        // Every new lock is OUT (no activity) and active.
        $this->assertSame(20, SpareLock::query()->where('board_id', $board->id)->whereNull('current_activity_id')->where('is_active', true)->count());
        $this->assertSame(20, $board->layout['board_columns']);
        $this->assertLessThanOrEqual(30, strlen($board->code));
    }

    public function test_add_board_form_has_only_division_business_unit_and_spare_count(): void
    {
        $businessUnit = BusinessUnit::factory()->create(['name' => 'Half Product']);
        $html = $this->actingAs($this->administrator())->get(route('safety.lototo.boards.index'))->assertOk()->getContent();
        preg_match('#<form method="POST" action="'.preg_quote(route('safety.lototo.boards.store'), '#').'".*?</form>#s', $html, $form);
        $this->assertNotEmpty($form);
        preg_match_all('#<(?:input|select)[^>]*name="([^"]+)"#', $form[0], $fields);
        $this->assertSame(['_token', 'division_name', 'business_unit_id', 'spare_lock_count'], $fields[1]);

        // Extra fields of the old form are ignored: the board gets the standard 20 columns.
        $this->actingAs($this->administrator())
            ->post(route('safety.lototo.boards.store'), [
                'division_name' => 'Half Product',
                'business_unit_id' => $businessUnit->id,
                'board_columns' => 15,
                'spare_code_prefix' => 'XX',
                'spare_lock_count' => 5,
            ])
            ->assertRedirect(route('safety.lototo.boards.index'));

        $board = LototoBoard::query()->where('division_name', 'Half Product')->firstOrFail();
        $this->assertSame([20, 20, 20], [$board->layout['board_columns'], $board->layout['technician_columns'], $board->layout['supervisor_columns']]);
        $this->assertSame(5, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'spare')->count());
        $this->assertSame('SP-HP-01', SpareLock::query()->where('board_id', $board->id)->orderBy('code')->value('code'));
        $this->assertSame(15, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'tool')->count());
        $this->assertSame(0, LototoTool::query()->where('board_id', $board->id)->count());
    }

    public function test_invalid_business_unit_and_duplicate_division_are_rejected(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create(['name' => 'Unit Two']);
        $payload = ['division_name' => 'Production', 'business_unit_id' => $businessUnit->id, 'spare_lock_count' => 2];

        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), [
            ...$payload,
            'business_unit_id' => 999999,
        ])->assertSessionHasErrors('business_unit_id');
        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), [
            ...$payload,
            'business_unit_id' => BusinessUnit::factory()->create(['is_active' => false])->id,
        ])->assertSessionHasErrors('business_unit_id');
        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), ['business_unit_id' => $businessUnit->id, 'spare_lock_count' => 2])->assertSessionHasErrors('division_name');

        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), $payload)->assertSessionHasErrors('division_name');
        $this->assertSame(1, LototoBoard::query()->where('division_name', 'Production')->count());
        $this->assertSame(2, SpareLock::query()->where('code', 'like', 'SP-UT-%')->count());
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
        $this->actingAs($staff)->patch(route('safety.lototo.boards.status', $board), ['is_active' => 0])->assertForbidden();
        $this->actingAs($staff)->delete(route('safety.lototo.boards.destroy', $board))->assertForbidden();
        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id, 'is_active' => true]);
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

        // An outstanding spare lock (its record closed, the lock still IN) counts as open work too.
        $spareLock->update(['current_activity_id' => $this->spareActivity($board, $spareLock, 'completed')->id]);

        // Manage Board shows the open activities, which the confirmation names before deactivating.
        $this->actingAs($admin)->get(route('safety.lototo.boards.index'))->assertOk()
            ->assertSee('2 aktivitas IN')
            ->assertSee('data-next-status="inactive" data-open-activities="2"', false);

        $this->actingAs($admin)->patch(route('safety.lototo.boards.status', $board), ['is_active' => '0'])
            ->assertRedirect(route('safety.lototo.boards.index'))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'OUT / Handover'));
        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id, 'is_active' => false, 'business_unit_id' => $businessUnit->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'employee_id' => $employee->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'employee_id' => $supervisor->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'spare_lock_id' => $spareLock->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'tool_label' => 'Torque wrench']);
        $this->assertDatabaseHas('spare_locks', ['id' => $spareLock->id, 'board_id' => $board->id]);
        $this->assertDatabaseHas('lototo_activities', ['board' => $board->code, 'status' => 'in_progress']);
        // The status action changes nothing else (no Business Unit or name change).
        $this->actingAs($admin)->patch(route('safety.lototo.boards.status', $board), ['is_active' => '0', 'business_unit_id' => $otherUnit->id, 'division_name' => 'X'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id, 'business_unit_id' => $businessUnit->id, 'division_name' => 'Maintenance']);
        $this->actingAs($admin)->patch(route('safety.lototo.boards.status', $board), ['is_active' => 'maybe'])->assertSessionHasErrors('is_active');
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

        // Not while its Business Unit is inactive.
        $businessUnit->update(['is_active' => false]);
        $this->actingAs($admin)->patch(route('safety.lototo.boards.status', $board), ['is_active' => '1'])->assertSessionHasErrors('is_active');
        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id, 'is_active' => false]);
        $businessUnit->update(['is_active' => true]);

        $this->actingAs($admin)->patch(route('safety.lototo.boards.status', $board), ['is_active' => '1', 'return_board' => $board->id])
            ->assertRedirect(route('safety.lototo.boards.index', ['board' => $board->id]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id, 'is_active' => true]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'employee_id' => $employee->id]);
        $this->assertSame(1, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'technician')->count());
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

    public function test_board_with_a_used_spare_lock_cannot_be_deleted(): void
    {
        $admin = $this->administrator();
        $board = $this->boardWithSpareLocks(BusinessUnit::factory()->create(), 2, 'USED');
        $board->update(['is_active' => false]);
        $used = SpareLock::query()->where('board_id', $board->id)->where('code', 'USED-02')->firstOrFail();
        // History on another board's activity log would not exist; the lock's own history blocks the delete.
        $activity = $this->spareActivity($board, $used, 'completed');
        $activity->update(['board' => 'another-board-code']);

        $this->actingAs($admin)->delete(route('safety.lototo.boards.destroy', $board))
            ->assertSessionHasErrors(['board' => "Board Maintenance tidak dapat dihapus: gembok Spare USED-02 sedang digunakan atau memiliki riwayat. Biarkan board nonaktif agar riwayat tetap tersimpan."]);

        $this->assertSame(2, SpareLock::query()->where('board_id', $board->id)->count());
        $this->assertSame(2, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'spare')->count());
        $this->assertDatabaseHas('lototo_boards', ['id' => $board->id]);
        $this->assertDatabaseHas('lototo_activities', ['id' => $activity->id, 'spare_lock_id' => $used->id]);
    }

    public function test_board_with_a_used_tool_cannot_be_deleted_but_unused_locks_and_tools_go_with_the_board(): void
    {
        $admin = $this->administrator();
        \Illuminate\Support\Facades\Storage::fake('local');
        $board = $this->boardWithSpareLocks(BusinessUnit::factory()->create(), 2, 'FREE');
        $board->update(['is_active' => false]);
        $tool = LototoTool::query()->create(['board_id' => $board->id, 'code' => 'TL-01', 'name' => 'Kunci', 'image_path' => 'lototo-tools/'.$board->id.'/kunci.png']);
        \Illuminate\Support\Facades\Storage::disk('local')->put($tool->image_path, 'png');
        $slot = LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'tool', 'position' => 1, 'tool_label' => 'Kunci', 'tool_id' => $tool->id, 'is_enabled' => true]);
        $history = LototoToolActivity::query()->create(['tool_id' => $tool->id, 'board_id' => $board->id, 'location' => 'Line A', 'status' => 'finished', 'started_by' => $admin->id, 'ended_by' => $admin->id, 'started_at' => now()->subHour(), 'ended_at' => now()]);

        $this->actingAs($admin)->delete(route('safety.lototo.boards.destroy', $board))
            ->assertSessionHasErrors('board');
        $this->assertDatabaseHas('lototo_tools', ['id' => $tool->id, 'board_id' => $board->id]);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'tool_id' => $tool->id]);
        $this->assertDatabaseHas('lototo_tool_activities', ['id' => $history->id]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($tool->image_path);

        // Without the history, the never-used tool (and its picture) and the never-used spare locks go with the board.
        $history->delete();
        $this->actingAs($admin)->delete(route('safety.lototo.boards.destroy', $board))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lototo_boards', ['id' => $board->id]);
        $this->assertDatabaseMissing('lototo_tools', ['id' => $tool->id]);
        $this->assertSame(0, SpareLock::query()->where('board_id', $board->id)->count());
        $this->assertDatabaseMissing('lototo_board_slots', ['board_id' => $board->id]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($tool->image_path);
    }

    public function test_add_board_creates_physical_spare_locks_with_codes_from_the_business_unit(): void
    {
        $businessUnit = BusinessUnit::factory()->create(['name' => 'Steel Cord', 'code' => 'BU-SC']);

        $this->actingAs($this->administrator())
            ->post(route('safety.lototo.boards.store'), [
                'division_name' => '  Dramix ',
                'business_unit_id' => $businessUnit->id,
                'spare_lock_count' => 3,
            ])
            ->assertSessionHasNoErrors();

        $board = LototoBoard::query()->where('business_unit_id', $businessUnit->id)->firstOrFail();
        $this->assertSame('Dramix', $board->division_name);
        $locks = SpareLock::query()->where('board_id', $board->id)->orderBy('code')->get();
        $this->assertSame(['SP-SC-01', 'SP-SC-02', 'SP-SC-03'], $locks->pluck('code')->all());
        $this->assertTrue($locks->every->is_active);
        $this->assertTrue($locks->every(fn (SpareLock $lock) => $lock->current_activity_id === null));
        foreach ($locks as $index => $lock) {
            $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $board->id, 'category' => 'spare', 'position' => $index + 1, 'spare_lock_id' => $lock->id]);
        }
        $this->assertSame(3, LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'spare')->count());

        // The QR of each lock identifies this lock on this board.
        $this->actingAs($this->administrator())
            ->get(route('safety.lototo.qr-codes', ['board' => $board->id]))
            ->assertOk()
            ->assertSee(e(\App\Http\Middleware\AllowLototoQrForm::url('safety.lototo.form', ['spare_lock' => 'SP-SC-02', 'board' => $board->id])), false);
        $this->actingAs($this->administrator())
            ->get(route('safety.lototo.form', ['spare_lock' => 'SP-SC-02', 'board' => $board->id]))
            ->assertOk()
            ->assertSee('name="board_id" value="'.$board->id.'"', false)
            ->assertSee('name="spare_lock_id" value="'.$locks[1]->id.'"', false);

        // Zero spare locks is allowed: no lock and no spare slot.
        $this->actingAs($this->administrator())
            ->post(route('safety.lototo.boards.store'), ['division_name' => 'Calendar', 'business_unit_id' => $businessUnit->id, 'spare_lock_count' => 0])
            ->assertSessionHasNoErrors();
        $empty = LototoBoard::query()->where('division_name', 'Calendar')->firstOrFail();
        $this->assertSame(0, SpareLock::query()->where('board_id', $empty->id)->count());
        $this->assertSame(0, LototoBoardSlot::query()->where('board_id', $empty->id)->where('category', 'spare')->count());
    }

    public function test_add_board_rejects_invalid_spare_count(): void
    {
        $businessUnit = BusinessUnit::factory()->create();
        $payload = ['division_name' => 'Maintenance', 'business_unit_id' => $businessUnit->id];
        $boards = LototoBoard::query()->count();

        foreach ([[], ['spare_lock_count' => ''], ['spare_lock_count' => -1], ['spare_lock_count' => 2.5], ['spare_lock_count' => 201], ['spare_lock_count' => 'ten']] as $invalid) {
            $this->actingAs($this->administrator())
                ->post(route('safety.lototo.boards.store'), [...$payload, ...$invalid])
                ->assertSessionHasErrors('spare_lock_count');
        }
        $this->assertSame($boards, LototoBoard::query()->count());
        $this->assertDatabaseCount('spare_locks', 0);
    }

    public function test_spare_codes_follow_the_business_unit_and_never_repeat_across_boards(): void
    {
        $admin = $this->administrator();
        $tireCord = BusinessUnit::factory()->create(['name' => 'Tire Cord', 'code' => 'BU-TC']);
        $halfProduct = BusinessUnit::factory()->create(['name' => 'Half Product', 'code' => 'BU-HP']);

        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), ['division_name' => 'Maintenance', 'business_unit_id' => $tireCord->id, 'spare_lock_count' => 2])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), ['division_name' => 'maintenance', 'business_unit_id' => $halfProduct->id, 'spare_lock_count' => 2])->assertSessionHasNoErrors();
        $first = LototoBoard::query()->where('business_unit_id', $tireCord->id)->firstOrFail();
        $second = LototoBoard::query()->where('business_unit_id', $halfProduct->id)->firstOrFail();

        $this->assertSame(['SP-TC-01', 'SP-TC-02'], SpareLock::query()->where('board_id', $first->id)->orderBy('code')->pluck('code')->all());
        $this->assertSame(['SP-HP-01', 'SP-HP-02'], SpareLock::query()->where('board_id', $second->id)->orderBy('code')->pluck('code')->all());
        $this->assertSame('SP-TC-03', $first->fresh()->nextSpareCodes(1)[0]);
        $this->assertSame('SP-HP-03', $second->fresh()->nextSpareCodes(1)[0]);
        // A second board of the same Business Unit continues the unit's series: its codes never repeat the first's.
        $this->actingAs($admin)->post(route('safety.lototo.boards.store'), ['division_name' => 'Electrical', 'business_unit_id' => $tireCord->id, 'spare_lock_count' => 2])->assertSessionHasNoErrors();
        $third = LototoBoard::query()->where('business_unit_id', $tireCord->id)->where('division_name', 'Electrical')->firstOrFail();
        $this->assertSame(['SP-TC-03', 'SP-TC-04'], SpareLock::query()->where('board_id', $third->id)->orderBy('code')->pluck('code')->all());
        // Codes of the older format stay as they are and do not disturb the series.
        SpareLock::query()->create(['board_id' => $first->id, 'code' => 'MAINTENANCE-SP-001', 'is_active' => true]);
        $this->assertSame('SP-TC-05', $first->fresh()->nextSpareCodes(1)[0]);
        $this->assertSame(['SP-TC-05', 'SP-TC-06'], $first->fresh()->nextSpareCodes(2));
        $this->assertSame([], $first->fresh()->nextSpareCodes(0));

        // The database refuses the same code twice, on any board.
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        SpareLock::query()->create(['board_id' => $second->id, 'code' => 'SP-TC-01', 'is_active' => true]);
    }

    public function test_the_unique_code_migration_stops_without_changes_while_two_boards_share_a_code(): void
    {
        $migration = require database_path('migrations/2026_11_05_000000_make_lototo_spare_lock_codes_unique_again.php');
        $migration->down();
        $first = $this->board(BusinessUnit::factory()->create());
        $second = $this->board(BusinessUnit::factory()->create());
        SpareLock::query()->create(['board_id' => $first->id, 'code' => 'SP-01', 'is_active' => true]);
        SpareLock::query()->create(['board_id' => $second->id, 'code' => 'SP-01', 'is_active' => true]);
        $before = SpareLock::query()->orderBy('id')->get()->toArray();

        try {
            $migration->up();
            $this->fail('The migration must stop while a code is shared.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('SP-01', $exception->getMessage());
        }
        $this->assertSame($before, SpareLock::query()->orderBy('id')->get()->toArray());
        $this->assertNotContains('spare_locks_code_unique', collect(\Illuminate\Support\Facades\Schema::getIndexes('spare_locks'))->pluck('name')->all());

        SpareLock::query()->where('board_id', $second->id)->update(['code' => 'SP-02']);
        $migration->up();
        $this->assertContains('spare_locks_code_unique', collect(\Illuminate\Support\Facades\Schema::getIndexes('spare_locks'))->pluck('name')->all());
    }

    public function test_a_failing_add_board_saves_nothing(): void
    {
        $businessUnit = BusinessUnit::factory()->create(['name' => 'Rollback Unit']);
        Employee::factory()->create(['business_unit_id' => $businessUnit->id, 'employment_status_id' => $this->activeStatus->id, 'lototo_number' => '05']);
        $counts = fn () => [LototoBoard::query()->count(), LototoBoardSlot::query()->count(), SpareLock::query()->count()];
        $before = $counts();
        // A spare lock code taken at the same moment by another request.
        SpareLock::creating(function (SpareLock $lock) {
            if ($lock->code === 'SP-RU-02') {
                throw new \Illuminate\Database\UniqueConstraintViolationException('sqlite', 'insert', [], new \Exception('duplicate'));
            }
        });

        $this->actingAs($this->administrator())
            ->post(route('safety.lototo.boards.store'), ['division_name' => 'Rollback', 'business_unit_id' => $businessUnit->id, 'spare_lock_count' => 3])
            ->assertSessionHasErrors('division_name');

        $this->assertSame($before, $counts());
        $this->assertDatabaseMissing('lototo_boards', ['division_name' => 'Rollback']);
    }

    public function test_inactive_board_cannot_receive_new_spare_locks(): void
    {
        $board = $this->boardWithSpareLocks(BusinessUnit::factory()->create(), 1, 'SP');
        $board->update(['is_active' => false]);

        $this->actingAs($this->administrator())->postJson(route('safety.lototo.boards.spare-locks.store', $board))
            ->assertUnprocessable()->assertJsonValidationErrors('spare_lock');
        $this->assertSame(1, SpareLock::query()->where('board_id', $board->id)->count());
    }

    public function test_unused_spare_lock_can_be_deleted_but_used_placed_or_foreign_locks_cannot(): void
    {
        $admin = $this->administrator();
        $businessUnit = BusinessUnit::factory()->create();
        $board = $this->boardWithSpareLocks($businessUnit, 3, 'SP');
        [$placed, $withHistory, $inUse] = SpareLock::query()->where('board_id', $board->id)->orderBy('code')->get()->all();
        $unused = SpareLock::query()->create(['board_id' => $board->id, 'code' => 'SP-09', 'is_active' => true]);
        // Locks with history or IN, taken off their slots, are still refused for their own reason.
        LototoBoardSlot::query()->whereIn('spare_lock_id', [$withHistory->id, $inUse->id])->update(['spare_lock_id' => null]);
        $history = $this->spareActivity($board, $withHistory, 'completed');
        $running = $this->spareActivity($board, $inUse, 'in_progress');
        $inUse->update(['current_activity_id' => $running->id]);
        $otherBoard = $this->boardWithSpareLocks(BusinessUnit::factory()->create(['name' => 'Other Unit']), 1, 'OTHER');
        $foreignLock = SpareLock::query()->where('board_id', $otherBoard->id)->firstOrFail();

        $this->actingAs($admin)->deleteJson(route('safety.lototo.boards.spare-locks.destroy', [$board, $unused]))
            ->assertOk()->assertJsonStructure(['message', 'next_spare_code']);
        $this->assertDatabaseMissing('spare_locks', ['id' => $unused->id]);

        foreach ([[$placed, 'masih terpasang di slot Spare 01'], [$withHistory, 'memiliki riwayat'], [$inUse, 'sedang IN']] as [$lock, $reason]) {
            $response = $this->actingAs($admin)->deleteJson(route('safety.lototo.boards.spare-locks.destroy', [$board, $lock]))->assertUnprocessable();
            $this->assertStringContainsString($reason, $response->json('errors.spare_lock.0'));
            $this->assertDatabaseHas('spare_locks', ['id' => $lock->id]);
        }
        $this->assertDatabaseHas('lototo_activities', ['id' => $history->id, 'spare_lock_id' => $withHistory->id]);
        $this->assertDatabaseHas('lototo_activities', ['id' => $running->id, 'spare_lock_id' => $inUse->id]);

        // A lock of another board cannot be deleted through this board.
        $this->actingAs($admin)->deleteJson(route('safety.lototo.boards.spare-locks.destroy', [$board, $foreignLock]))->assertNotFound();
        $this->assertDatabaseHas('spare_locks', ['id' => $foreignLock->id]);

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->actingAs($staff)->deleteJson(route('safety.lototo.boards.spare-locks.destroy', [$otherBoard, $foreignLock]))->assertForbidden();
    }

    public function test_manage_board_lists_only_open_status_and_delete_actions(): void
    {
        $admin = $this->administrator();
        $active = $this->board(BusinessUnit::factory()->create(['name' => 'Unit Aktif']));
        $inactive = $this->board(BusinessUnit::factory()->create(['name' => 'Unit Nonaktif']));
        $inactive->update(['is_active' => false]);

        $html = $this->actingAs($admin)->get(route('safety.lototo.boards.index', ['board' => $active->id]))->assertOk()
            ->assertSee('Manage LOTOTO Boards')
            ->assertSee('href="'.route('safety.lototo.boards.show', $active).'"', false)
            ->assertSee('action="'.route('safety.lototo.boards.status', $active).'"', false)
            ->assertSee('action="'.route('safety.lototo.boards.destroy', $inactive).'"', false)
            ->assertSee('Set Inactive')
            ->assertSee('Set Active')
            ->assertDontSee('Edit Board')
            ->assertDontSee('/edit"', false)
            ->getContent();
        // Delete is offered only once the board is inactive; the legacy board never has it.
        $this->assertMatchesRegularExpression('#action="'.preg_quote(route('safety.lototo.boards.destroy', $active), '#').'".*?<button\s+type="submit"\s+disabled#s', $html);
        $this->assertStringNotContainsString(route('safety.lototo.boards.destroy', LototoBoard::query()->where('code', 'mtn_tr')->value('id') ?? 0), $html);
        $this->assertStringContainsString('name="return_board" value="'.$active->id.'"', $html);
    }

    public function test_spare_locks_are_added_one_by_one_with_the_next_code_and_never_placed(): void
    {
        $admin = $this->administrator();
        $board = $this->board(BusinessUnit::factory()->create(['name' => 'Dramix']));
        $board->provisionSpareLocks([1, 2]);
        // A lock that already took code 04: the series continues after it (03 is not handed out later).
        SpareLock::query()->create(['board_id' => $board->id, 'code' => 'SP-DR-04', 'is_active' => true]);
        $slots = LototoBoardSlot::query()->where('board_id', $board->id)->count();

        $this->actingAs($admin)->getJson(route('safety.lototo.boards.layout', $board))->assertJsonPath('manage.next_spare_code', 'SP-DR-05');
        $this->actingAs($admin)->postJson(route('safety.lototo.boards.spare-locks.store', $board))
            ->assertCreated()
            ->assertJsonPath('spare_lock.code', 'SP-DR-05')
            ->assertJsonPath('spare_lock.slot', null)
            ->assertJsonPath('next_spare_code', 'SP-DR-06');
        $this->actingAs($admin)->postJson(route('safety.lototo.boards.spare-locks.store', $board))->assertJsonPath('spare_lock.code', 'SP-DR-06');

        $this->assertSame(['SP-DR-01', 'SP-DR-02', 'SP-DR-04', 'SP-DR-05', 'SP-DR-06'], SpareLock::query()->where('board_id', $board->id)->orderBy('code')->pluck('code')->all());
        $this->assertSame($slots, LototoBoardSlot::query()->where('board_id', $board->id)->count(), 'New spare locks are not put on a slot.');
        $this->assertNull(SpareLock::query()->where('code', 'SP-DR-06')->value('current_activity_id'));
        // Edit Slot offers the new lock.
        $this->actingAs($admin)->getJson(route('safety.lototo.boards.layout', $board))->assertJsonFragment(['code' => 'SP-DR-06']);
        $this->actingAs($admin)->get(route('safety.lototo.form', ['spare_lock' => 'SP-DR-06', 'board' => $board->id]))->assertOk();

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->actingAs($staff)->postJson(route('safety.lototo.boards.spare-locks.store', $board))->assertForbidden();
        $this->assertSame(5, SpareLock::query()->where('board_id', $board->id)->count());
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

    private function boardWithSpareLocks(BusinessUnit $businessUnit, int $count, string $prefix): LototoBoard
    {
        $board = $this->board($businessUnit);
        foreach (range(1, $count) as $position) {
            $lock = SpareLock::query()->create(['board_id' => $board->id, 'code' => sprintf('%s-%02d', $prefix, $position), 'is_active' => true]);
            LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'spare', 'position' => $position, 'spare_lock_id' => $lock->id, 'is_enabled' => true]);
        }

        return $board;
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
