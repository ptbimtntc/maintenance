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
use App\Support\LototoSlotGrid;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * A slot's category is what it holds; its section (band) is where it is. Changing the category of a slot (any
 * category, Technician included, unless something on it is IN) keeps its id, number, band and place in the
 * reading order. Every save packs each band in reading order (automatic layout): a wider Tools slot pushes the
 * slots after it along, a narrower or removed one lets them close up, and no gap is left.
 *
 * Board (columns): Technician 20, Supervisor 5, Spare 8, Other / Tools 6.
 *   Technician 1..3 at 1:1-3 (1 assigned).
 *   Supervisor 1..8: row 1 cols 1-5, row 2 cols 1-3 (1 assigned, the rest empty).
 *   Tools: 1 (span 2) at 1:1, 2 at 1:3, 3 at 1:4, 4 at 1:5, 5 at 1:6, 6 at 2:1, 7 (empty) at 2:4 (a gap at 2:2-3).
 *   Spare: 1..3 with locks SP-01..03 at 1:1-3; an empty slot numbered 5 at 1:8 (a gap at 1:4-7); SP-04 not placed.
 * The first save closes both gaps: Tools 7 to 2:2, Spare 5 to 1:4 (closeGaps).
 */
class LototoCategoryCellTest extends TestCase
{
    use RefreshDatabase;

    private LototoBoard $board;
    private User $admin;
    private SpareLock $freeLock;
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $unit = BusinessUnit::factory()->create(['is_active' => true, 'name' => 'Cells Unit']);
        $active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $unit->id, 'division_name' => 'Cells', 'code' => 'board-cells', 'title' => 'BOARD', 'subtitle' => 'Cells',
            'layout' => ['board_columns' => 20, 'technician_columns' => 20, 'supervisor_columns' => 5, 'spare_columns' => 8, 'tool_columns' => 6, 'section_order' => ['spare', 'supervisor', 'other']],
            'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $technician = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'lototo_number' => '01']);
        $supervisor = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'lototo_number' => '101']);

        foreach ([1, 2, 3] as $n) {
            $this->slot("technician{$n}", 'technician', $n, 1, $n, ['employee_id' => $n === 1 ? $technician->id : null]);
        }
        foreach (range(1, 8) as $n) {
            $this->slot("supervisor{$n}", 'supervisor', $n, $n <= 5 ? 1 : 2, $n <= 5 ? $n : $n - 5, ['employee_id' => $n === 1 ? $supervisor->id : null]);
        }
        foreach ([[1, 1, 1, 2], [2, 1, 3, 1], [3, 1, 4, 1], [4, 1, 5, 1], [5, 1, 6, 1], [6, 2, 1, 1], [7, 2, 4, 1]] as [$n, $row, $column, $span]) {
            $tool = $n === 7 ? null : LototoTool::query()->create(['board_id' => $this->board->id, 'code' => sprintf('TL-%02d', $n), 'name' => "Tool {$n}", 'is_active' => true]);
            $this->slot("tool{$n}", 'tool', $n, $row, $column, ['tool_column_span' => $span, 'tool_label' => $tool?->name, 'tool_id' => $tool?->id]);
        }
        foreach ([1, 2, 3] as $n) {
            $lock = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => sprintf('SP-%02d', $n), 'is_active' => true]);
            $this->slot("spare{$n}", 'spare', $n, 1, $n, ['spare_lock_id' => $lock->id]);
        }
        $this->slot('emptySpare5', 'spare', 5, 1, 8);
        $this->freeLock = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-04', 'is_active' => true]);
    }

    public function test_an_empty_supervisor_slot_becomes_a_tool_in_its_own_band_and_cell(): void
    {
        $before = $this->drawnCells();
        $rows = $this->rows();

        $this->putLayout($this->recategorise($this->draft(), 'supervisor7', 'tool', ['tool_id' => $this->tool('Kunci Torsi')]))->assertOk();

        // Same id, number 7 (Tools 7 exists too), same band and cell: Supervisor row 2 column 2, now holding a tool.
        $slot = LototoBoardSlot::query()->findOrFail($this->ids['supervisor7']);
        $this->assertSame(['tool', 'supervisor', 7, 2, 2], [$slot->category, $slot->section, $slot->position, $slot->grid_row, $slot->grid_column]);
        // The slot holds the chosen tool record and shows its name.
        $this->assertSame('Kunci Torsi', LototoTool::query()->findOrFail($slot->tool_id)->name);
        // Every other slot keeps its content; only the two gaps of the fixture close (automatic layout).
        $this->assertSame(collect($this->closeGaps($rows))->except($this->ids['supervisor7'])->all(), collect($this->rows())->except($this->ids['supervisor7'])->all());

        $after = $this->drawnCells();
        $this->assertSame(['supervisor', 2, 2, 1], $after[$this->ids['supervisor7']]);
        $this->assertSame(collect($this->closeGapCells($before))->except($this->ids['supervisor7'])->all(), collect($after)->except($this->ids['supervisor7'])->all());
        // Drawn as a tool card, with the tool look, inside the Supervisor band.
        $this->assertMatchesRegularExpression('/lototo-supervisor-area.*data-slot-category="tool"[^>]*data-slot-section="supervisor"[^>]*data-slot-id="'.$this->ids['supervisor7'].'".*lototo-card--as-tool.*Kunci Torsi.*lototo-other-group/s', $this->page());

        // Reloaded in the editor: listed as a tool, still in the Supervisor band and cell.
        $layout = $this->getJson(route('safety.lototo.boards.layout', $this->board))->assertOk()->json();
        $this->assertSame(['supervisor', 2, 2, 7], collect($layout['slots']['tool'])->where('id', $this->ids['supervisor7'])->map(fn ($s) => [$s['section'], $s['grid_row'], $s['grid_column'], $s['position']])->first());
    }

    public function test_empty_slots_become_spare_or_supervisor_in_place_and_take_the_right_content(): void
    {
        $rows = $this->rows();
        $draft = $this->recategorise($this->draft(), 'supervisor8', 'spare', ['spare_lock_id' => $this->freeLock->id]);
        $draft = $this->recategorise($draft, 'emptySpare5', 'supervisor', ['employee_id' => null]);
        $this->putLayout($draft)->assertOk();

        $spare = LototoBoardSlot::query()->findOrFail($this->ids['supervisor8']);
        $this->assertSame(['spare', 'supervisor', 8, 2, 3, $this->freeLock->id], [$spare->category, $spare->section, $spare->position, $spare->grid_row, $spare->grid_column, $spare->spare_lock_id]);
        $supervisor = LototoBoardSlot::query()->findOrFail($this->ids['emptySpare5']);
        // Still after Spare 3 in the Spare band; the gap before it closes (1:8 -> 1:4).
        $this->assertSame(['supervisor', 'spare', 5, 1, 4], [$supervisor->category, $supervisor->section, $supervisor->position, $supervisor->grid_row, $supervisor->grid_column]);
        $this->assertSame(collect($this->closeGaps($rows))->except([$this->ids['supervisor8'], $this->ids['emptySpare5']])->all(),
            collect($this->rows())->except([$this->ids['supervisor8'], $this->ids['emptySpare5']])->all());

        $html = $this->page();
        $this->assertMatchesRegularExpression('/lototo-supervisor-area.*data-slot-category="spare"[^>]*data-slot-section="supervisor".*lototo-card--as-spare.*Gembok spare SP-04/s', $html);
        $this->assertMatchesRegularExpression('/lototo-spare-group.*data-slot-category="supervisor"[^>]*data-slot-section="spare"[^>]*data-slot-id="'.$this->ids['emptySpare5'].'".*SUPERVISOR posisi 5 kosong/s', $html);
        $this->assertSame(['spare', 1, 4, 1], $this->drawnCells()[$this->ids['emptySpare5']]);
    }

    public function test_a_wider_tool_pushes_the_next_slots_along_and_a_narrower_one_lets_them_close_up(): void
    {
        $content = fn () => collect($this->rows())->map(fn ($row) => array_diff_key($row, ['grid_row' => 1, 'grid_column' => 1, 'tool_column_span' => 1]))->all();
        $before = $content();
        $tools = fn () => collect(range(1, 7))->mapWithKeys(fn ($n) => ["tool{$n}" => $this->cell("tool{$n}")])->all();

        // Tools 2 from one column to two: Tools 3..7 move along (Tools 5 no longer fits row 1); nothing is refused.
        $this->putLayout($this->edit($this->draft(), 'tool', 'tool2', ['tool_column_span' => 2]))->assertOk();
        $this->assertSame(['tool1' => '1:1', 'tool2' => '1:3', 'tool3' => '1:5', 'tool4' => '1:6', 'tool5' => '2:1', 'tool6' => '2:2', 'tool7' => '2:3'], $tools());
        $this->assertSame(2, (int) LototoBoardSlot::query()->find($this->ids['tool2'])->tool_column_span);
        $this->assertDrawnWithoutOverlap();

        // Back to one column: the slots after it close up again, no gap is left.
        $this->putLayout($this->edit($this->draft(), 'tool', 'tool2', ['tool_column_span' => 1]))->assertOk();
        $this->assertSame(['tool1' => '1:1', 'tool2' => '1:3', 'tool3' => '1:4', 'tool4' => '1:5', 'tool5' => '1:6', 'tool6' => '2:1', 'tool7' => '2:2'], $tools());

        // Supervisor 5 (1:5, the band's last column) as a two-column tool starts row 2; Supervisor 6..8 follow it.
        $this->putLayout($this->recategorise($this->draft(), 'supervisor5', 'tool', ['tool_id' => $this->tool('X'), 'tool_column_span' => 2]))->assertOk();
        $this->assertSame(['1:4', '2:1', '2:3', '2:4', '2:5'], collect(range(4, 8))->map(fn ($n) => $this->cell("supervisor{$n}"))->all());
        $this->assertDrawnWithoutOverlap();

        // Ids, numbers, categories and content never change; the editor preview reads the same cells.
        $this->assertSame(collect($before)->except($this->ids['supervisor5'])->all(), collect($content())->except($this->ids['supervisor5'])->all());
        $layout = $this->getJson(route('safety.lototo.boards.layout', $this->board))->json();
        $this->assertSame(['supervisor', 2, 1, 5], collect($layout['slots']['tool'])->where('id', $this->ids['supervisor5'])->map(fn ($s) => [$s['section'], $s['grid_row'], $s['grid_column'], $s['position']])->first());
    }

    public function test_technician_slots_can_change_category_or_be_removed_without_touching_records(): void
    {
        $employee = Employee::query()->findOrFail(LototoBoardSlot::query()->find($this->ids['technician1'])->employee_id);
        // A finished activity of that employee: history that must survive the slot changes.
        $history = LototoActivity::query()->create($this->activity($employee->id, 'completed'));
        $records = fn () => [Employee::query()->count(), LototoActivity::query()->count(), SpareLock::query()->count(), LototoTool::query()->count()];
        $recordsBefore = $records();

        // Technician 1 (holds an employee, nothing IN) becomes a spare slot with SP-04; Technician 2 is removed;
        // Technician 3 (empty) becomes a Supervisor slot. All stay in the Technician band, in reading order.
        $draft = $this->recategorise($this->draft(), 'technician1', 'spare', ['spare_lock_id' => $this->freeLock->id]);
        $draft = $this->recategorise($draft, 'technician3', 'supervisor', ['employee_id' => null]);
        $draft['removed'] = [$this->ids['technician2']];
        $draft['slots']['technician'] = array_values(array_filter($draft['slots']['technician'], fn ($slot) => $slot['id'] !== $this->ids['technician2']));
        $this->putLayout($draft)->assertOk();

        $one = LototoBoardSlot::query()->findOrFail($this->ids['technician1']);
        $three = LototoBoardSlot::query()->findOrFail($this->ids['technician3']);
        $this->assertSame(['spare', 'technician', 1, 1, 1, $this->freeLock->id, null], [$one->category, $one->section, $one->position, $one->grid_row, $one->grid_column, $one->spare_lock_id, $one->employee_id]);
        // Technician 2 is gone and Technician 3 (now Supervisor) closes up to 1:2.
        $this->assertNull(LototoBoardSlot::query()->find($this->ids['technician2']));
        $this->assertSame(['supervisor', 'technician', 3, 1, 2], [$three->category, $three->section, $three->position, $three->grid_row, $three->grid_column]);
        // Only slot rows changed: the employee, every activity (history included), every lock and tool remain.
        $this->assertSame($recordsBefore, $records());
        $this->assertNotNull($history->fresh());
        $this->assertNotNull($employee->fresh());

        // Drawn in the Technician band with their own look.
        $html = $this->page();
        $this->assertMatchesRegularExpression('/lototo-technician-grid.*data-slot-category="spare"[^>]*data-slot-section="technician"[^>]*data-slot-id="'.$this->ids['technician1'].'".*lototo-card--as-spare.*SP-04.*lototo-supervisor-area/s', $html);
        $this->assertMatchesRegularExpression('/lototo-technician-grid.*data-slot-category="supervisor"[^>]*data-slot-section="technician"[^>]*data-slot-id="'.$this->ids['technician3'].'".*lototo-supervisor-area/s', $html);

        // A slot of another band may become a Technician slot too.
        $this->putLayout($this->recategorise($this->draft(), 'supervisor6', 'technician', ['employee_id' => null]))->assertOk();
        $this->assertSame(['technician', 'supervisor'], [LototoBoardSlot::query()->find($this->ids['supervisor6'])->category, LototoBoardSlot::query()->find($this->ids['supervisor6'])->section]);
    }

    public function test_slots_with_something_in_and_moves_between_bands_are_refused_and_save_nothing(): void
    {
        // Technician 1's employee is IN; Spare 1's lock is in use; Tools 2's tool is IN.
        $employeeId = LototoBoardSlot::query()->find($this->ids['technician1'])->employee_id;
        LototoActivity::query()->create($this->activity($employeeId, 'in_progress'));
        $spareLock = SpareLock::query()->findOrFail(LototoBoardSlot::query()->find($this->ids['spare1'])->spare_lock_id);
        $spareLock->update(['current_activity_id' => LototoActivity::query()->create([...$this->activity($employeeId, 'in_progress'), 'spare_lock_id' => $spareLock->id])->id]);
        LototoToolActivity::query()->create(['tool_id' => LototoBoardSlot::query()->find($this->ids['tool2'])->tool_id, 'board_id' => $this->board->id,
            'location' => 'Line A', 'status' => 'in_progress', 'started_by' => $this->admin->id, 'started_at' => now('UTC')]);
        $rows = $this->rows();
        $remove = function (string $name, string $category) {
            $draft = $this->draft();
            $draft['removed'] = [$this->ids[$name]];
            $draft['slots'][$category] = array_values(array_filter($draft['slots'][$category], fn ($slot) => $slot['id'] !== $this->ids[$name]));

            return $draft;
        };
        $cases = [
            'technician with LOTOTO IN recategorised' => [$this->recategorise($this->draft(), 'technician1', 'supervisor', ['employee_id' => null]), '/Technician 1 memiliki aktivitas LOTOTO aktif \(IN\), sehingga kategorinya tidak dapat diganti/'],
            'technician with LOTOTO IN removed' => [$remove('technician1', 'technician'), '/Technician 1 memiliki aktivitas LOTOTO aktif \(IN\) dan tidak dapat dihapus/'],
            'spare lock in use recategorised' => [$this->recategorise($this->draft(), 'spare1', 'tool', ['tool_id' => null]), '/Spare 1 memiliki gembok Spare yang sedang digunakan/'],
            'spare lock in use removed' => [$remove('spare1', 'spare'), '/Spare 1 memiliki gembok Spare yang sedang digunakan \(IN\) dan tidak dapat dihapus/'],
            'tool IN removed' => [$remove('tool2', 'tool'), '/Other \/ Tools 2 memiliki Tools yang sedang IN dan tidak dapat dihapus/'],
            'slot moved to another band' => [$this->edit($this->draft(), 'supervisor', 'supervisor6', ['section' => 'spare']), '/tetap berada di pita Supervisor/'],
            'technician number changed' => [$this->edit($this->draft(), 'technician', 'technician3', ['position' => 9]), '/Posisi Technician saat ini mengikuti LOTOTO number/'],
        ];
        foreach ($cases as $name => [$draft, $pattern]) {
            $response = $this->putLayout($draft)->assertUnprocessable();
            $this->assertMatchesRegularExpression($pattern, implode(' ', $response->json('errors.slots') ?? []), $name);
            $this->assertSame($rows, $this->rows(), "{$name} changed the database");
        }
        // The editor gets the same flag, so its preview refuses them too.
        $layout = $this->getJson(route('safety.lototo.boards.layout', $this->board))->json();
        $locked = fn ($category, $name) => collect($layout['slots'][$category])->firstWhere('id', $this->ids[$name])['locked'];
        $this->assertSame([true, true, true, false], [$locked('technician', 'technician1'), $locked('spare', 'spare1'), $locked('tool', 'tool2'), $locked('tool', 'tool3')]);
    }

    public function test_a_new_column_count_packs_every_section_in_reading_order(): void
    {
        $count = LototoBoardSlot::query()->count();
        $content = fn () => collect($this->rows())->map(fn ($row) => array_diff_key($row, ['grid_row' => 1, 'grid_column' => 1]))->all();
        $before = $content();
        $cells = fn () => collect($this->rows())->mapWithKeys(fn ($row, $id) => [array_search($id, $this->ids, true) => $row['grid_row'].':'.$row['grid_column']])->all();
        $layoutColumns = fn () => array_map(fn ($key) => $this->board->fresh()->layout[$key], ['board_columns', 'technician_columns', 'supervisor_columns', 'spare_columns', 'tool_columns']);

        // A save that leaves the count alone keeps the per-band widths; the automatic layout only closes the two
        // gaps of the fixture (Tools 7 2:4 -> 2:2, Spare 5 1:8 -> 1:4).
        $cellsBefore = $cells();
        $this->putLayout($this->draft())->assertOk();
        $this->assertSame([20, 20, 5, 8, 6], $layoutColumns());
        $this->assertSame([...$cellsBefore, 'tool7' => '2:2', 'emptySpare5' => '1:4'], $cells());
        // A second save without changes moves nothing.
        $cellsPacked = $cells();
        $this->putLayout($this->draft())->assertOk();
        $this->assertSame($cellsPacked, $cells());

        // Edit Mode, 5 per row: every band takes 5 and its slots are packed in reading order. Tools 5 (1:6) no
        // longer fits row 1 and opens row 2; Tools 6 and 7 follow it; the empty Spare 5 closes the gap at 1:4.
        $draft = $this->draft();
        $draft['board_columns'] = 5;
        $draft['apply_columns'] = true;
        $this->putLayout($draft)->assertOk();
        $this->assertSame([5, 5, 5, 5, 5], $layoutColumns());
        $this->assertSame([
            'technician1' => '1:1', 'technician2' => '1:2', 'technician3' => '1:3',
            'supervisor1' => '1:1', 'supervisor2' => '1:2', 'supervisor3' => '1:3', 'supervisor4' => '1:4', 'supervisor5' => '1:5', 'supervisor6' => '2:1', 'supervisor7' => '2:2', 'supervisor8' => '2:3',
            'tool1' => '1:1', 'tool2' => '1:3', 'tool3' => '1:4', 'tool4' => '1:5', 'tool5' => '2:1', 'tool6' => '2:2', 'tool7' => '2:3',
            'spare1' => '1:1', 'spare2' => '1:2', 'spare3' => '1:3', 'emptySpare5' => '1:4',
        ], $cells());
        // Same slots, ids, categories, numbers and content; the board draws them where they are stored.
        $this->assertSame($count, LototoBoardSlot::query()->count());
        $this->assertSame($before, $content());
        $stored = collect($this->rows())->mapWithKeys(fn ($row, $id) => [$id => [$row['section'], $row['grid_row'], $row['grid_column'], (int) $row['tool_column_span'] > 1 && $row['category'] === 'tool' ? (int) $row['tool_column_span'] : 1]])->all();
        $this->assertEquals($stored, $this->drawnCells());
        $this->assertStringContainsString('--lototo-supervisor-columns: 5;', $this->page());

        // Again, 6 per row: the same rule from the stored cells.
        $draft = $this->draft();
        $draft['board_columns'] = 6;
        $draft['apply_columns'] = true;
        $this->putLayout($draft)->assertOk();
        $this->assertSame([6, 6, 6, 6, 6], $layoutColumns());
        $this->assertSame(['1:1', '1:3', '1:4', '1:5', '1:6', '2:1', '2:2'], collect(range(1, 7))->map(fn ($n) => $cells()["tool{$n}"])->all());
        $this->assertSame(['1:1', '1:2', '1:3', '1:4', '1:5', '1:6', '2:1', '2:2'], collect(range(1, 8))->map(fn ($n) => $cells()["supervisor{$n}"])->all());
        $layout = $this->getJson(route('safety.lototo.boards.layout', $this->board))->json();
        $this->assertSame([6, ['technician' => 6, 'supervisor' => 6, 'spare' => 6, 'tool' => 6]], [$layout['board_columns'], $layout['columns']]);
        $this->assertStringContainsString('--lototo-board-columns: 6;', $this->page());
        $this->assertSame($before, $content());

        // Below the minimum (5) the count is refused and nothing changes.
        $draft = $this->draft();
        $draft['board_columns'] = 4;
        $draft['apply_columns'] = true;
        $this->putLayout($draft)->assertUnprocessable()->assertJsonValidationErrors('board_columns');
        $this->assertSame([6, 6, 6, 6, 6], $layoutColumns());
    }

    public function test_slots_are_added_in_a_section_and_removed_and_the_slots_after_them_close_up(): void
    {
        $toolRecords = LototoTool::query()->orderBy('id')->get(['id', 'code', 'name'])->toArray();
        $rows = $this->rows();
        $draft = $this->draft();
        // Remove Supervisor 8 (empty, 2:3) and Tools 6 (holds TL-06, 2:1); add one empty Supervisor slot in the first
        // free cell (2:3, the one Supervisor 8 leaves), as the section "+" does.
        $draft['removed'] = [$this->ids['supervisor8'], $this->ids['tool6']];
        $draft['slots']['supervisor'] = array_values(array_filter($draft['slots']['supervisor'], fn ($slot) => $slot['id'] !== $this->ids['supervisor8']));
        $draft['slots']['tool'] = array_values(array_filter($draft['slots']['tool'], fn ($slot) => $slot['id'] !== $this->ids['tool6']));
        $draft['slots']['supervisor'][] = ['id' => null, 'position' => 9, 'section' => 'supervisor', 'grid_row' => 2, 'grid_column' => 3, 'employee_id' => null, 'is_enabled' => true];
        $this->putLayout($draft)->assertOk();

        $this->assertNull(LototoBoardSlot::query()->find($this->ids['supervisor8']));
        $this->assertNull(LototoBoardSlot::query()->find($this->ids['tool6']));
        // Its tool record stays, now without a place. Tools 7 closes up into the cell Tools 6 left (2:1); every
        // other slot keeps its content and place (only the fixture's Spare gap closes).
        $this->assertSame($toolRecords, LototoTool::query()->orderBy('id')->get(['id', 'code', 'name'])->toArray());
        $kept = collect($this->closeGaps($rows))->except([$this->ids['supervisor8'], $this->ids['tool6']])->all();
        $kept[$this->ids['tool7']]['grid_column'] = 1;
        $this->assertSame($kept, collect($this->rows())->only(array_keys($kept))->all());
        $added = LototoBoardSlot::query()->where('board_id', $this->board->id)->where('category', 'supervisor')->where('position', 9)->sole();
        $this->assertSame(['supervisor', 2, 3, null], [$added->section, $added->grid_row, $added->grid_column, $added->employee_id]);
        // Removed slots stay gone after reload: no empty placeholder comes back for them.
        $html = $this->page();
        $this->assertStringNotContainsString('data-slot-id="'.$this->ids['tool6'].'"', $html);
        $this->assertDoesNotMatchRegularExpression('/data-slot-category="tool" data-slot-position="6"/', $html);
        $this->assertArrayNotHasKey($this->ids['supervisor8'], $this->getJson(route('safety.lototo.boards.layout', $this->board))->json('slots.supervisor'));

        // A Technician slot can be removed too: Technician 3 goes, nothing else in the band moves.
        $draft = $this->draft();
        $draft['removed'] = [$this->ids['technician3']];
        $draft['slots']['technician'] = array_values(array_filter($draft['slots']['technician'], fn ($slot) => $slot['id'] !== $this->ids['technician3']));
        $this->putLayout($draft)->assertOk();
        $this->assertNull(LototoBoardSlot::query()->find($this->ids['technician3']));
        $this->assertSame(['1:1', '1:2'], [$this->cell('technician1'), $this->cell('technician2')]);
        // The stale draft (Technician 3 already gone) is refused and saves nothing.
        $rows = $this->rows();
        $this->putLayout($draft)->assertStatus(409);
        $this->assertSame($rows, $this->rows());
    }

    public function test_kelola_tools_and_kelola_spare_lock_only_create_data_that_the_popup_offers(): void
    {
        $slots = $this->rows();
        $tool = $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'Gerinda Baru'], ['Accept' => 'application/json'])->assertCreated()->json('tool');
        $this->assertSame(['TL-07', 'Gerinda Baru', null], [$tool['code'], $tool['name'], $tool['slot']]);
        $this->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'gerinda baru'], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => ' '], ['Accept' => 'application/json'])->assertUnprocessable();
        $next = $this->getJson(route('safety.lototo.boards.layout', $this->board))->json('manage.next_spare_code');
        $this->assertSame('SP-CU-01', $next);
        $lock = $this->postJson(route('safety.lototo.boards.spare-locks.store', $this->board))->assertCreated()->json('spare_lock');
        $this->assertSame([$next, null, false], [$lock['code'], $lock['slot'], $lock['in']]);
        // Data only: no slot was made or filled.
        $this->assertSame($slots, $this->rows());
        $this->assertSame(1, LototoTool::query()->where('board_id', $this->board->id)->where('code', 'TL-07')->count());
        $layout = $this->getJson(route('safety.lototo.boards.layout', $this->board))->json();
        $this->assertContains($tool['id'], array_column($layout['options']['tool'], 'id'));
        $this->assertContains($lock['id'], array_column($layout['options']['spare'], 'id'));
        $this->assertContains($tool['id'], array_column($layout['manage']['tools'], 'id'));
        $this->assertContains($lock['id'], array_column($layout['manage']['spare_locks'], 'id'));

        // Placed through the popup: the empty Tools 7 takes the new tool, the empty Spare 5 the new lock.
        $draft = $this->edit($this->edit($this->draft(), 'tool', 'tool7', ['tool_id' => $tool['id']]), 'spare', 'emptySpare5', ['spare_lock_id' => $lock['id']]);
        $this->putLayout($draft)->assertOk();
        $this->assertSame([$tool['id'], 'Gerinda Baru'], [LototoBoardSlot::query()->find($this->ids['tool7'])->tool_id, LototoBoardSlot::query()->find($this->ids['tool7'])->tool_label]);
        $this->assertSame($lock['id'], LototoBoardSlot::query()->find($this->ids['emptySpare5'])->spare_lock_id);
        // Once placed, neither can be deleted from the popups.
        $this->deleteJson(route('safety.lototo.boards.tools.destroy', [$this->board, $tool['id']]))->assertUnprocessable();
        $this->deleteJson(route('safety.lototo.boards.spare-locks.destroy', [$this->board, $lock['id']]))->assertUnprocessable();
        // One tool in one slot only.
        $draft = $this->edit($this->draft(), 'tool', 'tool2', ['tool_id' => $tool['id']]);
        $this->assertStringContainsString('tidak boleh ditempatkan pada lebih dari satu posisi', implode(' ', $this->putLayout($draft)->assertUnprocessable()->json('errors.slots')));

        // Only Safety managers; an inactive board takes no new spare lock.
        $staff = User::factory()->create();
        $this->actingAs($staff)->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'X'], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($staff)->postJson(route('safety.lototo.boards.spare-locks.store', $this->board))->assertForbidden();
        $this->board->update(['is_active' => false]);
        $this->actingAs($this->admin)->postJson(route('safety.lototo.boards.spare-locks.store', $this->board))->assertUnprocessable();
    }

    public function test_bands_below_technician_keep_any_saved_order(): void
    {
        $draft = $this->draft();
        $draft['section_order'] = ['other', 'supervisor', 'spare'];
        $this->putLayout($draft)->assertOk();
        $this->assertSame(['other', 'supervisor', 'spare'], $this->board->fresh()->layout['section_order']);
        $this->assertSame(['other', 'supervisor', 'spare'], $this->draft()['section_order']);
        preg_match_all('/class="[^"]*lototo-(other-group|supervisor-area|spare-group)[^"]*"\s*style="[^"]*order: (\d+);/', $this->page(), $m, PREG_SET_ORDER);
        $this->assertSame(['other-group' => 2, 'supervisor-area' => 3, 'spare-group' => 4], collect($m)->mapWithKeys(fn ($x) => [$x[1] => (int) $x[2]])->sort()->all());
    }

    public function test_a_stale_version_is_refused_without_partial_changes(): void
    {
        $stale = $this->recategorise($this->draft(), 'supervisor7', 'tool', ['tool_id' => $this->tool('A')]);
        $this->putLayout($this->recategorise($this->draft(), 'supervisor8', 'tool', ['tool_id' => $this->tool('B')]))->assertOk();
        $rows = $this->rows();

        $this->putLayout($stale)->assertStatus(409);
        $this->assertSame($rows, $this->rows());
        $this->assertSame('supervisor', LototoBoardSlot::query()->find($this->ids['supervisor7'])->category);
    }

    public function test_tools_and_spare_lock_management_never_move_slots_arranged_in_edit_mode(): void
    {
        $kunci = $this->tool('Kunci');
        $this->putLayout($this->recategorise($this->draft(), 'supervisor7', 'tool', ['tool_id' => $kunci]))->assertOk();
        $cells = $this->drawnCells();
        $json = ['Accept' => 'application/json'];

        // Renaming a placed tool changes its label only; adding a tool or a spare lock places nothing.
        $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.update', [$this->board, $kunci]), ['name' => 'Kunci Baru'], $json)->assertOk()
            ->assertJsonPath('tool.slot', 'Other / Tools 07');
        $this->assertSame('Kunci Baru', LototoBoardSlot::query()->find($this->ids['supervisor7'])->tool_label);
        $this->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'Gerinda'], $json)->assertCreated();
        $this->postJson(route('safety.lototo.boards.spare-locks.store', $this->board))->assertCreated();
        $this->assertNull(LototoBoardSlot::query()->find($this->ids['tool7'])->tool_id);
        $this->assertSame($cells, $this->drawnCells());

        // Deleting the unplaced spare lock SP-04 and the new tool leaves every slot where it was.
        $this->deleteJson(route('safety.lototo.boards.spare-locks.destroy', [$this->board, $this->freeLock]))->assertOk();
        $this->deleteJson(route('safety.lototo.boards.tools.destroy', [$this->board, LototoTool::query()->where('name', 'Gerinda')->value('id')]))->assertOk();
        $stored = LototoBoardSlot::query()->where('board_id', $this->board->id)->orderBy('id')->get()
            ->mapWithKeys(fn ($s) => [$s->id => [LototoSlotGrid::sectionOf($s), $s->grid_row, $s->grid_column, LototoSlotGrid::span($s, $s->category)]])->all();
        $this->assertSame($stored, $this->drawnCells());
        $this->assertSame($cells, $this->drawnCells());
    }

    public function test_the_section_migration_rolls_back_only_while_every_slot_sits_in_its_own_band(): void
    {
        $migration = require database_path('migrations/2026_11_04_000002_add_section_to_lototo_board_slots.php');
        $this->assertContains('lototo_slots_board_section_cell_unique', collect(Schema::getIndexes('lototo_board_slots'))->pluck('name'));
        $this->putLayout($this->recategorise($this->draft(), 'supervisor7', 'tool', ['tool_id' => $this->tool('Kunci')]))->assertOk();
        $rows = $this->rows();

        try {
            $migration->down();
            $this->fail('Rollback should be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('1 LOTOTO slot(s) sit in a band other than their category', $exception->getMessage());
        }
        $this->assertSame($rows, $this->rows());

        DB::table('lototo_board_slots')->where('id', $this->ids['supervisor7'])->update(['category' => 'supervisor', 'tool_id' => null, 'tool_label' => null]);
        $rows = collect($this->rows())->map(fn ($row) => collect($row)->except('section')->all())->all();
        $migration->down();
        LototoSlotGrid::forgetSchema();
        $this->assertFalse(Schema::hasColumn('lototo_board_slots', 'section'));
        $this->assertContains('lototo_slots_board_category_cell_unique', collect(Schema::getIndexes('lototo_board_slots'))->pluck('name'));
        $this->assertSame($rows, collect(DB::table('lototo_board_slots')->where('board_id', $this->board->id)->orderBy('id')
            ->get(['id', 'category', 'position', 'grid_row', 'grid_column', 'employee_id', 'spare_lock_id', 'tool_id', 'tool_label', 'tool_column_span', 'is_enabled'])
            ->mapWithKeys(fn ($r) => [$r->id => (array) $r]))->all());
        $migration->up();
        LototoSlotGrid::forgetSchema();
        $this->assertSame(0, DB::table('lototo_board_slots')->whereColumn('section', '!=', 'category')->count());
    }

    /** $rows (from rows()) after the automatic layout closes the fixture's two gaps. */
    private function closeGaps(array $rows): array
    {
        $rows[$this->ids['tool7']]['grid_column'] = 2;
        $rows[$this->ids['emptySpare5']]['grid_column'] = 4;

        return $rows;
    }

    /** drawnCells() after the automatic layout closes the fixture's two gaps. */
    private function closeGapCells(array $cells): array
    {
        $cells[$this->ids['tool7']][2] = 2;
        $cells[$this->ids['emptySpare5']][2] = 4;

        return $cells;
    }

    /** "row:column" stored for slot $name. */
    private function cell(string $name): string
    {
        $slot = LototoBoardSlot::query()->findOrFail($this->ids[$name]);

        return "{$slot->grid_row}:{$slot->grid_column}";
    }

    /** Every band as drawn on the board: no cell is used twice and every slot is inside its band's columns. */
    private function assertDrawnWithoutOverlap(): void
    {
        $columns = ['technician' => 20, 'supervisor' => 5, 'spare' => 8, 'tool' => 6];
        $taken = [];
        foreach ($this->drawnCells() as $id => [$band, $row, $column, $span]) {
            $this->assertLessThanOrEqual($columns[$band], $column + $span - 1, "slot {$id} leaves {$band}");
            foreach (range($column, $column + $span - 1) as $c) {
                $this->assertArrayNotHasKey("{$band}:{$row}:{$c}", $taken, "slot {$id} overlaps slot ".($taken["{$band}:{$row}:{$c}"] ?? ''));
                $taken["{$band}:{$row}:{$c}"] = $id;
            }
        }
    }

    private function activity(int $employeeId, string $status): array
    {
        return ['employee_id' => $employeeId, 'board' => $this->board->code, 'location' => 'Line A', 'activity' => 'Ganti bearing',
            'risks' => ['listrik'], 'risk_controls' => 'LOTO', 'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true,
            'safe_tools' => true, 'safe_hands' => true, 'status' => $status, 'started_at' => now('UTC'), 'ended_at' => $status === 'completed' ? now('UTC') : null];
    }

    private function slot(string $name, string $category, int $position, int $row, int $column, array $extra = []): void
    {
        $this->ids[$name] = DB::table('lototo_board_slots')->insertGetId([
            'board_id' => $this->board->id, 'category' => $category, 'section' => $category, 'position' => $position, 'grid_row' => $row, 'grid_column' => $column,
            'is_enabled' => true, 'tool_column_span' => 1, 'created_at' => now(), 'updated_at' => now(), ...$extra,
        ]);
    }

    private function draft(): array
    {
        $layout = $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))->assertOk()->json();
        $this->assertTrue($layout['coordinates'] && $layout['sections']);

        return [...collect($layout)->only(['version', 'title', 'subtitle', 'board_columns', 'section_order', 'components', 'slots'])->all(), 'apply_columns' => false];
    }

    /** Changes slot $name (empty) to $category as the popup does: same entry, number, band and cell. */
    private function recategorise(array $draft, string $name, string $category, array $changes): array
    {
        foreach ($draft['slots'] as $from => $slots) {
            foreach ($slots as $index => $slot) {
                if ($slot['id'] === $this->ids[$name]) {
                    unset($draft['slots'][$from][$index]);
                    $draft['slots'][$from] = array_values($draft['slots'][$from]);
                    $draft['slots'][$category][] = [...$slot, 'employee_id' => null, 'spare_lock_id' => null, 'tool_id' => null, 'tool_label' => null, ...$changes];

                    return $draft;
                }
            }
        }
        $this->fail("Slot {$name} not in the draft.");
    }

    private function edit(array $draft, string $category, string $name, array $changes): array
    {
        foreach ($draft['slots'][$category] as $index => $slot) {
            if ($slot['id'] === $this->ids[$name]) {
                $draft['slots'][$category][$index] = [...$slot, ...$changes];
            }
        }

        return $draft;
    }

    /** A tool record of the board (as Kelola Tools makes it), by name. */
    private function tool(string $name): int
    {
        return LototoTool::query()->firstOrCreate(['board_id' => $this->board->id, 'name' => $name], ['code' => 'TX-'.strtoupper(substr(md5($name), 0, 6)), 'is_active' => true])->id;
    }

    private function putLayout(array $draft)
    {
        return $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft);
    }

    private function rows(): array
    {
        return DB::table('lototo_board_slots')->where('board_id', $this->board->id)->orderBy('id')
            ->get(['id', 'category', 'section', 'position', 'grid_row', 'grid_column', 'employee_id', 'spare_lock_id', 'tool_id', 'tool_label', 'tool_column_span', 'is_enabled'])
            ->mapWithKeys(fn ($r) => [$r->id => (array) $r])->all();
    }

    private function page(): string
    {
        return $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();
    }

    /** [slot id => [band, row, column, span]] as the board page draws every saved slot. */
    private function drawnCells(): array
    {
        $html = $this->page();
        $bands = ['technician' => 'lototo-technician-grid', 'supervisor' => 'lototo-supervisor-area', 'spare' => 'lototo-spare-group', 'tool' => 'lototo-other-group'];
        $starts = collect($bands)->map(fn ($marker) => strpos($html, $marker))->filter(fn ($start) => $start !== false)->sort();
        $cells = [];
        foreach ($starts as $band => $start) {
            $end = $starts->first(fn ($other) => $other > $start) ?? strlen($html);
            preg_match_all('/data-slot-id="(\d+)"[^>]*?style="grid-column: (\d+)(?: \/ span (\d+))?; grid-row: (\d+);/s', substr($html, $start, $end - $start), $found, PREG_SET_ORDER);
            foreach ($found as $m) {
                $cells[(int) $m[1]] = [$band, (int) $m[4], (int) $m[2], $m[3] !== '' ? (int) $m[3] : 1];
            }
        }
        ksort($cells);

        return $cells;
    }
}
