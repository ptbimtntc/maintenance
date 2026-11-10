<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\LototoTool;
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
 * A slot's category is what it holds; its section (band) and cell are where it is. Changing the category of an
 * empty slot keeps its id, number, band and cell. Column counts only widen or narrow a band, never move slots.
 *
 * Board (columns): Technician 20, Supervisor 5, Spare 8, Other / Tools 6.
 *   Supervisor 1..8: row 1 cols 1-5, row 2 cols 1-3 (1 assigned, the rest empty).
 *   Tools: 1 (span 2) at 1:1, 2 at 1:3, 3 at 1:4, 4 at 1:5, 5 at 1:6, 6 at 2:1, 7 (empty) at 2:4.
 *   Spare: 1..3 with locks SP-01..03 at 1:1-3; an empty slot numbered 5 at 1:8; lock SP-04 not placed.
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
        $unit = BusinessUnit::factory()->create(['is_active' => true]);
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
        $this->assertSame(collect($rows)->except($this->ids['supervisor7'])->all(), collect($this->rows())->except($this->ids['supervisor7'])->all());

        $after = $this->drawnCells();
        $this->assertSame(['supervisor', 2, 2, 1], $after[$this->ids['supervisor7']]);
        $this->assertSame(collect($before)->except($this->ids['supervisor7'])->all(), collect($after)->except($this->ids['supervisor7'])->all());
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
        $this->assertSame(['supervisor', 'spare', 5, 1, 8], [$supervisor->category, $supervisor->section, $supervisor->position, $supervisor->grid_row, $supervisor->grid_column]);
        $this->assertSame(collect($rows)->except([$this->ids['supervisor8'], $this->ids['emptySpare5']])->all(),
            collect($this->rows())->except([$this->ids['supervisor8'], $this->ids['emptySpare5']])->all());

        $html = $this->page();
        $this->assertMatchesRegularExpression('/lototo-supervisor-area.*data-slot-category="spare"[^>]*data-slot-section="supervisor".*lototo-card--as-spare.*Gembok spare SP-04/s', $html);
        $this->assertMatchesRegularExpression('/lototo-spare-group.*data-slot-category="supervisor"[^>]*data-slot-section="spare"[^>]*data-slot-id="'.$this->ids['emptySpare5'].'".*SUPERVISOR posisi 5 kosong/s', $html);
        $this->assertSame(['spare', 1, 8, 1], $this->drawnCells()[$this->ids['emptySpare5']]);
    }

    public function test_real_overlaps_band_edges_and_locked_rules_are_refused_and_save_nothing(): void
    {
        $rows = $this->rows();
        $cases = [
            // Supervisor 2 (1:2) as a two-column tool would cover Supervisor 3 on 1:3.
            'wide tool over a neighbour' => [$this->recategorise($this->draft(), 'supervisor2', 'tool', ['tool_id' => $this->tool('X'), 'tool_column_span' => 2]), '/Supervisor 2 \(menjadi Other \/ Tools\) bertabrakan dengan Supervisor 3 pada baris 1 kolom 3/'],
            // Supervisor 5 (1:5) as a two-column tool would leave the five-column Supervisor band.
            'wide tool past the band edge' => [$this->recategorise($this->draft(), 'supervisor5', 'tool', ['tool_id' => $this->tool('X'), 'tool_column_span' => 2]), '/selebar 2 kolom mulai kolom 5 melewati batas pita Supervisor \(5 kolom\)/'],
            'tool widened over a neighbour' => [$this->edit($this->draft(), 'tool', 'tool2', ['tool_column_span' => 2]), '/bertabrakan dengan Other \/ Tools 2|Other \/ Tools 2 bertabrakan/'],
            'tool widened past the edge' => [$this->edit($this->draft(), 'tool', 'tool5', ['tool_column_span' => 2]), '/selebar 2 kolom mulai kolom 6 melewati batas pita Other \/ Tools \(6 kolom\)/'],
            'technician cell moved' => [$this->edit($this->draft(), 'technician', 'technician2', ['grid_row' => 2, 'grid_column' => 1]), '/Posisi Technician 2 dikunci/'],
            'technician recategorised' => [$this->recategorise($this->draft(), 'technician3', 'supervisor', ['employee_id' => null]), '/bukan posisi Technician/'],
            'slot moved to another band' => [$this->edit($this->draft(), 'supervisor', 'supervisor6', ['section' => 'spare']), '/tetap berada di pita Supervisor/'],
            'empty slot made a technician' => [$this->recategorise($this->draft(), 'supervisor6', 'technician', ['employee_id' => null]), '/bukan posisi Technician/'],
        ];
        foreach ($cases as $name => [$draft, $pattern]) {
            $response = $this->putLayout($draft)->assertUnprocessable();
            $this->assertMatchesRegularExpression($pattern, implode(' ', $response->json('errors.slots') ?? []), $name);
            $this->assertSame($rows, $this->rows(), "{$name} changed the database");
        }
    }

    public function test_a_new_column_count_packs_every_section_in_reading_order(): void
    {
        $count = LototoBoardSlot::query()->count();
        $content = fn () => collect($this->rows())->map(fn ($row) => array_diff_key($row, ['grid_row' => 1, 'grid_column' => 1]))->all();
        $before = $content();
        $cells = fn () => collect($this->rows())->mapWithKeys(fn ($row, $id) => [array_search($id, $this->ids, true) => $row['grid_row'].':'.$row['grid_column']])->all();
        $layoutColumns = fn () => array_map(fn ($key) => $this->board->fresh()->layout[$key], ['board_columns', 'technician_columns', 'supervisor_columns', 'spare_columns', 'tool_columns']);

        // A save that leaves the count alone keeps the per-band widths and every cell.
        $cellsBefore = $cells();
        $this->putLayout($this->draft())->assertOk();
        $this->assertSame([20, 20, 5, 8, 6], $layoutColumns());
        $this->assertSame($cellsBefore, $cells());

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

    public function test_slots_are_added_in_a_section_and_removed_one_by_one_leaving_their_cell_empty(): void
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
        // Its tool record stays, now without a place; no other slot moved or changed.
        $this->assertSame($toolRecords, LototoTool::query()->orderBy('id')->get(['id', 'code', 'name'])->toArray());
        $this->assertSame(collect($rows)->except([$this->ids['supervisor8'], $this->ids['tool6']])->all(), collect($this->rows())->only(array_keys(collect($rows)->except([$this->ids['supervisor8'], $this->ids['tool6']])->all()))->all());
        $added = LototoBoardSlot::query()->where('board_id', $this->board->id)->where('category', 'supervisor')->where('position', 9)->sole();
        $this->assertSame(['supervisor', 2, 3, null], [$added->section, $added->grid_row, $added->grid_column, $added->employee_id]);
        // Removed slots stay gone after reload: no empty placeholder takes their cell (Tools 2:1 stays empty).
        $html = $this->page();
        $this->assertStringNotContainsString('data-slot-id="'.$this->ids['tool6'].'"', $html);
        $this->assertDoesNotMatchRegularExpression('/data-slot-category="tool" data-slot-position="6" data-grid-row="2" data-grid-column="1"/', $html);
        $this->assertArrayNotHasKey($this->ids['supervisor8'], $this->getJson(route('safety.lototo.boards.layout', $this->board))->json('slots.supervisor'));

        // A Technician slot cannot be removed; a stale removal (slot already gone) is refused as a changed list.
        $draft = $this->draft();
        $draft['removed'] = [$this->ids['technician3']];
        $draft['slots']['technician'] = array_values(array_filter($draft['slots']['technician'], fn ($slot) => $slot['id'] !== $this->ids['technician3']));
        $rows = $this->rows();
        $response = $this->putLayout($draft)->assertUnprocessable();
        $this->assertStringContainsString('Technician 3 tidak dapat dihapus', implode(' ', $response->json('errors.slots')));
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
        $this->assertSame('CELLS-SP-001', $next);
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
