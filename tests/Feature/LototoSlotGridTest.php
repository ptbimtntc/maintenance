<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\SpareLock;
use App\Models\User;
use App\Support\LototoSlotGrid;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** grid_row / grid_column: the backfill and every slot change store exactly the cell the board view draws. */
class LototoSlotGridTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_04_000000_add_grid_coordinates_to_lototo_board_slots.php';

    private BusinessUnit $unit;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->unit = BusinessUnit::factory()->create(['is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
    }

    public function test_the_backfill_stores_the_cell_each_board_draws_and_changes_nothing_else(): void
    {
        $migration = $this->gridMigrations();
        $migration->down();
        LototoSlotGrid::forgetSchema();
        $this->assertFalse(Schema::hasColumn('lototo_board_slots', 'grid_row'));

        // One board on the old single column count, one with per-category columns, wide tools and a gap.
        $legacy = $this->board('board-grid-legacy', ['board_columns' => 15, 'technician_columns' => 15, 'supervisor_columns' => 15, 'section_order' => ['spare', 'other']]);
        $mixed = $this->board('board-grid-mixed', ['board_columns' => 7, 'technician_columns' => 7, 'supervisor_columns' => 5, 'spare_columns' => 8, 'tool_columns' => 6,
            'section_order' => ['other', 'spare', 'supervisor']]);
        $this->slots($legacy, ['technician' => range(1, 33), 'supervisor' => range(1, 17), 'spare' => range(1, 16), 'tool' => range(1, 16)]);
        $this->slots($mixed, ['technician' => range(1, 15), 'supervisor' => range(1, 12), 'spare' => range(1, 9), 'tool' => [1, 2, 3, 5, 6, 7, 8]],
            spans: [1 => 2, 3 => 3, 6 => 2, 8 => 3]);
        $before = $this->dataSnapshot();

        $migration->up();
        LototoSlotGrid::forgetSchema();

        $this->assertTrue(Schema::hasColumn('lototo_board_slots', 'grid_row'));
        $this->assertContains('lototo_slots_board_section_cell_unique', collect(Schema::getIndexes('lototo_board_slots'))->pluck('name'));
        $this->assertSame($before, $this->dataSnapshot());
        foreach ([$legacy, $mixed] as $board) {
            $this->assertCellsMatchBoard($board);
        }
        // Six Tools columns, flowing with spans and the missing position 4 taking one column:
        // row 1: 1 (span 2) 2 3 (span 3) | row 2: gap 5 6 (span 2) 7 | row 3: 8 (span 3).
        $tool = fn ($position) => LototoBoardSlot::query()->where('board_id', $mixed->id)->where('category', 'tool')->where('position', $position)->first(['grid_row', 'grid_column'])->only(['grid_row', 'grid_column']);
        $this->assertSame(['grid_row' => 1, 'grid_column' => 1], $tool(1));
        $this->assertSame(['grid_row' => 1, 'grid_column' => 3], $tool(2));
        $this->assertSame(['grid_row' => 1, 'grid_column' => 4], $tool(3));
        $this->assertSame(['grid_row' => 2, 'grid_column' => 2], $tool(5));
        $this->assertSame(['grid_row' => 2, 'grid_column' => 3], $tool(6));
        $this->assertSame(['grid_row' => 2, 'grid_column' => 5], $tool(7));
        $this->assertSame(['grid_row' => 3, 'grid_column' => 1], $tool(8));

        // Running it again changes nothing.
        $cells = $this->cells();
        $migration->up();
        $this->assertSame($cells, $this->cells());
    }

    public function test_rollback_drops_the_cells_only_while_no_slot_sits_away_from_its_position_number(): void
    {
        $migration = $this->gridMigrations();
        $board = $this->board('board-grid-rollback', ['board_columns' => 10]);
        $this->slots($board, ['supervisor' => range(1, 3)]);
        $slot = LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'supervisor')->where('position', 3)->firstOrFail();
        $slot->forceFill(['grid_row' => 4, 'grid_column' => 9])->save();

        try {
            $migration->down();
            $this->fail('Rollback should be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('1 LOTOTO slot(s) are placed away from their position number', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('lototo_board_slots', 'grid_row'));

        $slot->forceFill(['grid_row' => 1, 'grid_column' => 3])->save();
        $before = $this->dataSnapshot();
        $migration->down();
        LototoSlotGrid::forgetSchema();
        $this->assertFalse(Schema::hasColumn('lototo_board_slots', 'grid_row'));
        $this->assertSame($before, $this->dataSnapshot());

        // Code running before the migration (database not migrated yet): saves work and write no cells.
        $layout = $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $board))->assertOk()->json();
        $draft = [...collect($layout)->only(['version', 'title', 'subtitle', 'board_columns', 'section_order', 'components', 'slots'])->all(), 'apply_columns' => false];
        $draft['slots']['supervisor'][] = ['id' => null, 'position' => 4, 'employee_id' => null, 'spare_lock_id' => null, 'tool_label' => null, 'tool_column_span' => 1, 'is_enabled' => true];
        $this->putJson(route('safety.lototo.boards.layout.update', $board), $draft)->assertOk();
        $this->assertSame(4, LototoBoardSlot::query()->where('board_id', $board->id)->count());

        $migration->up();
        LototoSlotGrid::forgetSchema();
        $this->assertCellsMatchBoard($board, 'migrating a board that changed before the migration');
    }

    public function test_every_slot_change_keeps_the_stored_cells_equal_to_the_board(): void
    {
        $this->actingAs($this->admin)->post(route('safety.lototo.boards.store'), [
            'division_name' => 'Grid', 'business_unit_id' => $this->unit->id, 'spare_lock_count' => 12,
        ])->assertSessionHasNoErrors();
        $board = LototoBoard::query()->where('division_name', 'Grid')->firstOrFail();
        $this->assertCellsMatchBoard($board, 'new board');

        $steps = [
            'tools: add a tool (not placed)' => fn () => $this->post(route('safety.lototo.boards.tools.store', $board), ['name' => 'Gerinda'], ['Accept' => 'application/json'])->assertCreated(),
            'spare: add 3 locks (not placed)' => function () use ($board) {
                foreach (range(1, 3) as $n) {
                    $response = $this->postJson(route('safety.lototo.boards.spare-locks.store', $board))->assertCreated();
                }

                return $response;
            },
            'spare: delete an unplaced lock' => fn () => $this->deleteJson(route('safety.lototo.boards.spare-locks.destroy', [$board, \App\Models\SpareLock::query()->where('board_id', $board->id)->orderByDesc('id')->value('id')]))->assertOk(),
            'tools: rename the tool' => fn () => $this->post(route('safety.lototo.boards.tools.update', [$board, \App\Models\LototoTool::query()->where('board_id', $board->id)->value('id')]), ['name' => 'Gerinda Tangan'], ['Accept' => 'application/json'])->assertOk(),
            'edit mode: category, columns, wide tool' => function () use ($board) {
                $layout = $this->getJson(route('safety.lototo.boards.layout', $board))->json();
                $draft = [...collect($layout)->only(['version', 'title', 'subtitle', 'board_columns', 'section_order', 'components', 'slots'])->all(), 'apply_columns' => false];
                // A new column count (slots packed in reading order), and an empty Supervisor slot becomes a wide tool
                // (a tool record made with Kelola Tools) on a free row of its band.
                $draft['board_columns'] = 14;
                $draft['apply_columns'] = true;
                $empty = collect($draft['slots']['supervisor'])->firstWhere('employee_id', null);
                $draft['slots']['supervisor'] = array_values(array_filter($draft['slots']['supervisor'], fn ($s) => $s['id'] !== $empty['id']));
                $freeRow = collect($draft['slots']['tool'])->max('grid_row') + 1;
                $tool = \App\Models\LototoTool::query()->create(['board_id' => $board->id, 'code' => 'TL-91', 'name' => 'Kunci Torsi', 'is_active' => true]);
                $draft['slots']['tool'][] = [...$empty, 'position' => 40, 'tool_id' => $tool->id, 'tool_column_span' => 3, 'grid_row' => $freeRow, 'grid_column' => 1];

                return $this->putJson(route('safety.lototo.boards.layout.update', $board), $draft)->assertOk();
            },
        ];
        foreach ($steps as $name => $step) {
            $this->actingAs($this->admin);
            $response = $step();
            if ($response->status() === 302) {
                $response->assertSessionHasNoErrors();
            }
            $this->assertCellsMatchBoard($board, $name);
        }
    }

    /** The cell migrations as one: down() runs them in reverse order (as migrate:rollback does), up() in order. */
    private function gridMigrations(): object
    {
        $files = [self::MIGRATION, '2026_11_04_000001_allow_repeated_lototo_slot_numbers_per_category.php', '2026_11_04_000002_add_section_to_lototo_board_slots.php'];
        $migrations = array_map(fn ($file) => require database_path('migrations/'.$file), $files);

        return new class($migrations) {
            public function __construct(private array $migrations) {}

            public function up(): void
            {
                foreach ($this->migrations as $migration) {
                    $migration->up();
                    LototoSlotGrid::forgetSchema();
                }
            }

            public function down(): void
            {
                foreach (array_reverse($this->migrations) as $migration) {
                    $migration->down();
                    LototoSlotGrid::forgetSchema();
                }
            }
        };
    }

    private function board(string $code, array $layout): LototoBoard
    {
        return LototoBoard::query()->create([
            'business_unit_id' => $this->unit->id, 'division_name' => $code, 'code' => $code, 'title' => 'BOARD', 'subtitle' => 'Grid',
            'layout' => $layout, 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
    }

    private function slots(LototoBoard $board, array $positions, array $spans = []): void
    {
        foreach ($positions as $category => $list) {
            foreach ($list as $position) {
                $lock = $category === 'spare' ? SpareLock::query()->create(['board_id' => $board->id, 'code' => sprintf('G%d-%02d', $board->id, $position), 'is_active' => true]) : null;
                DB::table('lototo_board_slots')->insert([
                    'board_id' => $board->id, 'category' => $category, 'position' => $position, 'is_enabled' => true, 'spare_lock_id' => $lock?->id,
                    'tool_label' => $category === 'tool' ? "Tool {$position}" : null, 'tool_column_span' => $category === 'tool' ? ($spans[$position] ?? 1) : 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /** Every slot of the board has a stored cell, and it is the cell (and span) the board page draws it in. */
    private function assertCellsMatchBoard(LototoBoard $board, string $step = ''): void
    {
        $html = $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $board))->assertOk()->getContent();
        preg_match_all('/data-slot-id="(\d+)"[^>]*?style="grid-column: (\d+)(?: \/ span \d+)?; grid-row: (\d+);/s', $html, $drawn, PREG_SET_ORDER);
        $drawnCells = collect($drawn)->mapWithKeys(fn ($m) => [(int) $m[1] => [(int) $m[3], (int) $m[2]]])->sortKeys()->all();
        $stored = LototoBoardSlot::query()->where('board_id', $board->id)->orderBy('id')->get()
            ->mapWithKeys(fn ($slot) => [$slot->id => [$slot->grid_row, $slot->grid_column]])->all();

        $this->assertNotEmpty($stored, $step);
        $this->assertNotContains([null, null], $stored, "Slots without a cell after: {$step}");
        $this->assertSame($stored, $drawnCells, "Stored cells differ from the board after: {$step}");
    }

    private function cells(): array
    {
        return LototoBoardSlot::query()->orderBy('id')->get(['id', 'grid_row', 'grid_column'])->toArray();
    }

    private function dataSnapshot(): array
    {
        return [
            DB::table('lototo_board_slots')->orderBy('id')->get(['id', 'board_id', 'category', 'position', 'employee_id', 'spare_lock_id', 'tool_id', 'tool_label', 'tool_column_span', 'is_enabled'])->map(fn ($r) => (array) $r)->all(),
            DB::table('spare_locks')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('lototo_boards')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }
}
