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
use App\Models\SpareLock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LototoBoardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $unit;
    private EmploymentStatus $active;
    private LototoBoard $board;
    private LototoBoard $otherBoard;
    private User $admin;
    private Employee $technician;
    private Employee $secondTechnician;
    private Employee $supervisor;
    private SpareLock $spareOne;
    private SpareLock $spareTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->unit = BusinessUnit::factory()->create();
        $this->active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->board = $this->makeBoard('board-layout-test');
        $this->otherBoard = $this->makeBoard('board-layout-other');
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);

        $this->technician = $this->employee('01');
        $this->secondTechnician = $this->employee('02');
        $this->supervisor = $this->employee('101');
        $this->spareOne = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-01', 'is_active' => true]);
        $this->spareTwo = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-02', 'is_active' => true]);

        $this->slot('technician', 1, ['employee_id' => $this->technician->id]);
        $this->slot('technician', 2, ['employee_id' => $this->secondTechnician->id]);
        $this->slot('technician', 3);
        $this->slot('supervisor', 1, ['employee_id' => $this->supervisor->id]);
        $this->slot('supervisor', 2);
        $this->slot('spare', 1, ['spare_lock_id' => $this->spareOne->id]);
        $this->slot('spare', 2, ['spare_lock_id' => $this->spareTwo->id]);
        $this->slot('tool', 1);
        LototoBoardSlot::query()->create(['board_id' => $this->otherBoard->id, 'category' => 'technician', 'position' => 1, 'is_enabled' => true]);
    }

    public function test_only_safety_managers_can_read_or_save_the_layout(): void
    {
        $draft = $this->draft();
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $before = $this->snapshot();

        $this->actingAs($staff)->getJson(route('safety.lototo.boards.layout', $this->board))->assertForbidden();
        $this->actingAs($staff)->putJson(route('safety.lototo.boards.layout.update', $this->board), ['title' => 'X'] + $draft)->assertForbidden();
        auth()->logout();
        $this->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)->assertUnauthorized();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_the_layout_endpoint_returns_the_configuration_options_and_version(): void
    {
        $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))
            ->assertOk()
            ->assertJsonPath('board_columns', 20)
            ->assertJsonPath('columns', ['technician' => 20, 'supervisor' => 20, 'spare' => 20, 'tool' => 20])
            ->assertJsonPath('manage.spare_locks.0.code', 'SP-01')
            ->assertJsonPath('manage.spare_locks.1.slot', 'Spare 02')
            ->assertJsonPath('manage.next_spare_code', 'BOARD-LAYOUT-TEST-SP-001')
            ->assertJsonPath('manage.tools', [])
            ->assertJsonPath('section_order', ['spare', 'supervisor', 'other'])
            ->assertJsonPath('column_limits', ['min' => 5, 'max' => 30])
            ->assertJsonPath('slots.technician.0.employee_id', $this->technician->id)
            ->assertJsonPath('slots.spare.1.spare_lock_id', $this->spareTwo->id)
            ->assertJsonCount(2, 'options.technician')
            ->assertJsonCount(1, 'options.supervisor')
            ->assertJsonCount(2, 'options.spare')
            ->assertJsonStructure(['version']);
    }

    public function test_a_valid_draft_is_saved_in_one_request(): void
    {
        $draft = $this->draft();
        $draft['title'] = 'BOARD BARU';
        $draft['subtitle'] = 'Subtitle baru';
        // One column count for the whole board ("Kolom per baris"): every band takes it.
        $draft['board_columns'] = 12;
        $draft['apply_columns'] = true;
        $draft['section_order'] = ['other', 'spare', 'supervisor'];
        $draft['components']['other'] = false;
        // Spare locks swap positions, the second technician moves to the empty slot 3 (assignments, not positions).
        $draft['slots']['spare'][0]['spare_lock_id'] = $this->spareTwo->id;
        $draft['slots']['spare'][1]['spare_lock_id'] = $this->spareOne->id;
        $draft['slots']['technician'][1]['employee_id'] = null;
        $draft['slots']['technician'][2]['employee_id'] = $this->secondTechnician->id;
        // New slots, and the empty supervisor slot 2 becomes Other/Tools position 2 holding a tool record ("+ Tools").
        $draft['slots']['technician'][] = $this->newSlot(4);
        $emptySupervisor = array_pop($draft['slots']['supervisor']);
        $tool = LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-02', 'name' => 'Kunci Torsi', 'is_active' => true]);
        $draft['slots']['tool'][] = ['id' => $emptySupervisor['id'], 'position' => 2, 'tool_id' => $tool->id, 'tool_column_span' => 2, 'is_enabled' => true];

        $response = $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)
            ->assertOk()
            ->assertJsonPath('redirect', route('safety.lototo.boards.show', $this->board));

        $board = $this->board->fresh();
        $this->assertSame('BOARD BARU', $board->title);
        $this->assertSame('Subtitle baru', $board->subtitle);
        $this->assertSame([12, 12, 12, 12, 12], [$board->layout['board_columns'], $board->layout['technician_columns'], $board->layout['supervisor_columns'], $board->layout['spare_columns'], $board->layout['tool_columns']]);
        $this->assertSame(['other', 'spare', 'supervisor'], $board->layout['section_order']);
        $this->assertSame(['technician' => true, 'supervisor' => true, 'other' => false, 'spare' => true], $board->component_settings);
        $this->assertSlot('spare', 1, ['spare_lock_id' => $this->spareTwo->id]);
        $this->assertSlot('spare', 2, ['spare_lock_id' => $this->spareOne->id]);
        $this->assertSlot('technician', 1, ['employee_id' => $this->technician->id]);
        $this->assertSlot('technician', 2, ['employee_id' => null]);
        $this->assertSlot('technician', 3, ['employee_id' => $this->secondTechnician->id]);
        $this->assertSlot('technician', 4, ['employee_id' => null]);
        $this->assertSlot('tool', 2, ['id' => $emptySupervisor['id'], 'tool_label' => 'Kunci Torsi', 'tool_column_span' => 2]);
        $this->assertSame(1, LototoBoardSlot::query()->where('board_id', $this->board->id)->where('category', 'supervisor')->count());
        $this->assertSame(['TL-02', 'Kunci Torsi'], [$tool->fresh()->code, $tool->fresh()->name]);
        $this->assertSame(1, LototoTool::query()->where('board_id', $this->board->id)->count());
        $this->assertSlot('tool', 2, ['tool_id' => $tool->id]);
        // The other board is untouched and the returned version is the one the editor reads next.
        $this->assertSame(1, LototoBoardSlot::query()->where('board_id', $this->otherBoard->id)->count());
        $this->assertSame($response->json('version'), $this->draft()['version']);
    }

    public function test_invalid_drafts_are_refused_and_save_nothing(): void
    {
        $outsider = Employee::factory()->create(['business_unit_id' => BusinessUnit::factory()->create()->id, 'employment_status_id' => $this->active->id, 'lototo_number' => '05']);
        $cases = [
            'too few columns' => fn ($d) => ['board_columns' => 4, 'apply_columns' => true] + $d,
            'too many columns' => fn ($d) => ['board_columns' => 31, 'apply_columns' => true] + $d,
            'count changed without applying it' => fn ($d) => ['board_columns' => 15] + $d,
            'section missing' => fn ($d) => ['section_order' => ['spare', 'spare', 'other']] + $d,
            'employee of another business unit' => fn ($d) => $this->withSlot($d, 'technician', 2, ['employee_id' => $outsider->id]),
            'supervisor in a technician slot' => fn ($d) => $this->withSlot($d, 'technician', 2, ['employee_id' => $this->supervisor->id]),
            'technician in a supervisor slot' => fn ($d) => $this->withSlot($d, 'supervisor', 1, ['employee_id' => $this->technician->id]),
            'same employee twice' => fn ($d) => $this->withSlot($d, 'technician', 2, ['employee_id' => $this->technician->id]),
            'same spare twice' => fn ($d) => $this->withSlot($d, 'spare', 2, ['spare_lock_id' => $this->spareOne->id]),
            'duplicate position' => fn ($d) => $this->withSlot($d, 'supervisor', 2, ['position' => 1]),
            'technician position moved' => fn ($d) => $this->withSlot($this->withSlot($d, 'technician', 3, ['position' => 9]), 'technician', 1, []),
            'slot of another board' => fn ($d) => $this->withSlot($d, 'technician', 3, ['id' => LototoBoardSlot::query()->where('board_id', $this->otherBoard->id)->value('id')]),
            'slot left out' => fn ($d) => ['slots' => ['technician' => array_slice($d['slots']['technician'], 0, 2)] + $d['slots']] + $d,
            'filled slot changes category' => function ($d) {
                $supervisorSlot = array_shift($d['slots']['supervisor']);
                $d['slots']['tool'][] = ['position' => 5, 'tool_label' => null, 'is_enabled' => true] + $supervisorSlot;

                return $d;
            },
            'technician slot changes category' => function ($d) {
                $technicianSlot = array_pop($d['slots']['technician']);
                $d['slots']['supervisor'][] = ['position' => 5] + $technicianSlot;

                return $d;
            },
        ];
        $before = $this->snapshot();

        foreach ($cases as $name => $change) {
            $draft = $change($this->draft());
            $draft['title'] = 'TIDAK BOLEH TERSIMPAN';
            $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)
                ->assertUnprocessable();
            $this->assertSame($before, $this->snapshot(), "Case '{$name}' changed the database.");
        }
    }

    public function test_slots_with_an_active_lock_cannot_be_cleared_or_moved_but_the_rest_still_saves(): void
    {
        $this->inProgress($this->supervisor);
        $this->spareOne->update(['current_activity_id' => $this->inProgress($this->technician, $this->spareOne)->id]);
        $before = $this->snapshot();

        foreach ([
            fn ($d) => $this->withSlot($d, 'supervisor', 1, ['employee_id' => null]),
            fn ($d) => $this->withSlot($this->withSlot($d, 'supervisor', 1, ['employee_id' => null]), 'supervisor', 2, ['employee_id' => $this->supervisor->id]),
            fn ($d) => $this->withSlot($d, 'supervisor', 1, ['position' => 3]),
            fn ($d) => $this->withSlot($d, 'supervisor', 1, ['is_enabled' => false]),
            fn ($d) => $this->withSlot($this->withSlot($d, 'spare', 1, ['spare_lock_id' => $this->spareTwo->id]), 'spare', 2, ['spare_lock_id' => $this->spareOne->id]),
            fn ($d) => $this->withSlot($d, 'spare', 1, ['position' => 5]),
        ] as $index => $change) {
            $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $change($this->draft()))
                ->assertUnprocessable();
            $this->assertSame($before, $this->snapshot(), "Active-lock case {$index} changed the database.");
        }

        // Leaving the active slots as they are, other changes go through and the activities keep their locks.
        $draft = $this->withSlot($this->draft(), 'spare', 2, ['position' => 4]);
        $draft['title'] = 'DENGAN AKTIVITAS AKTIF';
        $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)->assertOk();
        $this->assertSame('DENGAN AKTIVITAS AKTIF', $this->board->fresh()->title);
        $this->assertSlot('spare', 4, ['spare_lock_id' => $this->spareTwo->id]);
        $this->assertSlot('spare', 1, ['spare_lock_id' => $this->spareOne->id]);
        $this->assertSlot('supervisor', 1, ['employee_id' => $this->supervisor->id]);
        $this->assertNotNull($this->spareOne->fresh()->current_activity_id);
    }

    public function test_a_save_based_on_an_older_version_is_refused(): void
    {
        $draft = $this->draft();
        // Another administrator saves the board in Edit Mode in the meantime.
        $other = $this->withSlot($this->withSlot($this->draft(), 'supervisor', 1, ['employee_id' => null]), 'supervisor', 2, ['employee_id' => $this->supervisor->id]);
        $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $other)->assertOk();
        $before = $this->snapshot();

        $draft['title'] = 'TIMPA';
        $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Konfigurasi board sudah diubah oleh pengguna lain. Muat ulang board lalu ulangi perubahan Anda.');
        $this->assertSame($before, $this->snapshot());
        $this->assertSlot('supervisor', 2, ['employee_id' => $this->supervisor->id]);

        // The same draft sent twice: the second one is stale too.
        $fresh = [...$this->draft(), 'title' => 'BARU'];
        $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $fresh)->assertOk();
        $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), ['title' => 'LAGI'] + $fresh)->assertStatus(409);
    }

    public function test_an_inactive_board_takes_no_new_assignment_but_keeps_its_layout_editable(): void
    {
        $this->board->update(['is_active' => false]);
        $newcomer = $this->employee('03');
        $before = $this->snapshot();

        $draft = $this->withSlot($this->draft(), 'technician', 3, ['employee_id' => $newcomer->id]);
        $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)
            ->assertUnprocessable()->assertJsonValidationErrors('slots');
        $this->assertSame($before, $this->snapshot());
        $this->assertSlot('technician', 3, ['employee_id' => null]);

        // Existing assignments stay; titles and other non-assignment changes still save.
        $draft = $this->draft();
        $draft['title'] = 'BOARD NONAKTIF';
        $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)->assertOk();
        $this->assertSame('BOARD NONAKTIF', $this->board->fresh()->title);
        $this->assertSlot('technician', 1, ['employee_id' => $this->technician->id]);
    }

    public function test_a_rule_failing_after_writes_started_rolls_everything_back(): void
    {
        // A failure while the new slot is written, after the board and every other slot were written already.
        $tool = LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-01', 'name' => 'Gerinda', 'is_active' => true]);
        LototoBoardSlot::query()->where('board_id', $this->board->id)->where('category', 'tool')->update(['tool_id' => $tool->id, 'tool_label' => 'Gerinda']);
        $before = $this->snapshot();

        $draft = $this->draft();
        $draft['title'] = 'TIDAK BOLEH TERSIMPAN';
        $draft['board_columns'] = 7;
        $draft['apply_columns'] = true;
        $draft = $this->withSlot($this->withSlot($draft, 'spare', 1, ['spare_lock_id' => $this->spareTwo->id]), 'spare', 2, ['spare_lock_id' => $this->spareOne->id]);
        $draft['slots']['supervisor'][] = $this->newSlot(3);
        LototoBoardSlot::creating(fn () => throw new \RuntimeException('Simulated failure while saving the new slot.'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft);
            $this->fail('The save should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated failure while saving the new slot.', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertSame('Gerinda', $tool->fresh()->name);
    }

    private function makeBoard(string $code): LototoBoard
    {
        return LototoBoard::query()->create([
            'business_unit_id' => $this->unit->id, 'division_name' => $code, 'code' => $code, 'title' => 'BOARD', 'subtitle' => 'Test',
            'layout' => ['board_columns' => 20, 'section_order' => ['spare', 'other']],
            'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
    }

    private function employee(string $lototoNumber): Employee
    {
        return Employee::factory()->create(['business_unit_id' => $this->unit->id, 'employment_status_id' => $this->active->id, 'lototo_number' => $lototoNumber]);
    }

    private function slot(string $category, int $position, array $attributes = []): LototoBoardSlot
    {
        return LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => $category, 'position' => $position, 'is_enabled' => true, ...$attributes]);
    }

    private function inProgress(Employee $employee, ?SpareLock $spareLock = null): LototoActivity
    {
        return LototoActivity::query()->create([
            'employee_id' => $employee->id, 'spare_lock_id' => $spareLock?->id, 'board' => $this->board->code, 'location' => 'Pump', 'activity' => 'Valve',
            'risks' => ['terjepit'], 'risk_controls' => 'Lock', 'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true,
            'safe_tools' => true, 'safe_hands' => true, 'status' => 'in_progress', 'started_at' => now(),
        ]);
    }

    /** What the editor holds after loading the board: the layout endpoint's answer, sent back unchanged. */
    private function draft(): array
    {
        $layout = $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))->assertOk()->json();

        return [...collect($layout)->only(['version', 'title', 'subtitle', 'board_columns', 'section_order', 'components', 'slots'])->all(), 'apply_columns' => false];
    }

    private function withSlot(array $draft, string $category, int $position, array $changes): array
    {
        foreach ($draft['slots'][$category] as $index => $slot) {
            if ($slot['position'] === $position) {
                $draft['slots'][$category][$index] = [...$slot, ...$changes];
            }
        }

        return $draft;
    }

    private function newSlot(int $position): array
    {
        return ['id' => null, 'position' => $position, 'employee_id' => null, 'spare_lock_id' => null, 'tool_label' => null, 'tool_column_span' => 1, 'is_enabled' => true];
    }

    private function assertSlot(string $category, int $position, array $expected): void
    {
        $slot = LototoBoardSlot::query()->where('board_id', $this->board->id)->where('category', $category)->where('position', $position)->sole();
        foreach ($expected as $column => $value) {
            $this->assertEquals($value, $slot->getAttribute($column), "{$category} {$position}: {$column}");
        }
    }

    private function snapshot(): array
    {
        return [
            LototoBoard::query()->orderBy('id')->get()->map->only(['id', 'title', 'subtitle', 'layout', 'component_settings'])->all(),
            LototoBoardSlot::query()->orderBy('id')->get()->map->only(['id', 'board_id', 'category', 'position', 'employee_id', 'spare_lock_id', 'tool_id', 'tool_label', 'tool_column_span', 'is_enabled'])->all(),
            LototoTool::query()->orderBy('id')->get()->map->only(['id', 'code', 'name'])->all(),
            SpareLock::query()->orderBy('id')->get()->map->only(['id', 'code', 'current_activity_id'])->all(),
        ];
    }
}
