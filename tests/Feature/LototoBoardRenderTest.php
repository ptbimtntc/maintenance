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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LototoBoardRenderTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $unit;
    private EmploymentStatus $active;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->unit = BusinessUnit::factory()->create(['is_active' => true]);
        $this->active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
    }

    public function test_a_board_saved_before_per_category_columns_uses_its_board_columns_and_puts_supervisor_after_spare(): void
    {
        $board = $this->board(['board_columns' => 15, 'technician_columns' => 15, 'supervisor_columns' => 15, 'section_order' => ['spare', 'other']]);
        $this->slots($board, technician: 17, supervisor: 17, spare: 16, tool: 16);

        $html = $this->page($board);

        $this->assertStringContainsString('--lototo-board-columns: 15;', $html);
        $this->assertSame(['technician' => 15, 'spare' => 15, 'other' => 15], $this->sectionColumns($html));
        $this->assertStringContainsString('--lototo-supervisor-columns: 15;', $html);
        $this->assertSame(['spare' => 2, 'supervisor' => 3, 'other' => 4], $this->sectionOrder($html));
        // Supervisor wraps after 15 slots, 01 at the left of the first row, 16 at the left of the second.
        $this->assertSame([1 => [1, 1], 15 => [15, 1], 16 => [1, 2], 17 => [2, 2]], $this->supervisorCells($html, [1, 15, 16, 17]));
        $this->assertStringContainsString('grid-column: 1; grid-row: 2;', $this->section($html, 'spare'));
        $this->assertStringNotContainsString('lototo-page--wide', $html);
        $this->assertSame(17, substr_count($this->section($html, 'technician'), 'lototo-position-index'));
        $this->assertSame(17, substr_count($this->section($html, 'supervisor'), 'lototo-position-index'));
    }

    public function test_each_category_uses_its_own_column_count_and_the_board_is_as_wide_as_the_widest_visible_one(): void
    {
        $board = $this->board(['board_columns' => 12, 'technician_columns' => 12, 'supervisor_columns' => 5, 'spare_columns' => 8, 'tool_columns' => 6,
            'section_order' => ['other', 'spare', 'supervisor']]);
        $this->slots($board, technician: 13, supervisor: 7, spare: 9, tool: 7);

        $html = $this->page($board);

        $this->assertStringContainsString('--lototo-board-columns: 12;', $html);
        $this->assertSame(['technician' => 12, 'spare' => 8, 'other' => 6], $this->sectionColumns($html));
        $this->assertStringContainsString('--lototo-supervisor-columns: 5;', $html);
        // Other first as saved, Supervisor still right after Spare.
        $this->assertSame(['other' => 2, 'spare' => 3, 'supervisor' => 4], $this->sectionOrder($html));
        $this->assertSame([5 => [5, 1], 6 => [1, 2], 7 => [2, 2]], $this->supervisorCells($html, [5, 6, 7]));
        $this->assertStringContainsString('grid-column: 1; grid-row: 2;', $this->section($html, 'spare'));
        $this->assertStringContainsString('grid-column: 1; grid-row: 2;', $this->section($html, 'technician'));
        $this->assertStringContainsString('grid-column: 1 / span 1; grid-row: 2;', $this->section($html, 'other'));
        $this->assertStringContainsString('SUPERVISOR posisi 6 kosong', $html);

        // A hidden category does not widen the board; a visible one over 20 columns makes the page scroll.
        $board->update(['layout' => [...$board->layout, 'tool_columns' => 30], 'component_settings' => [...$board->component_settings, 'other' => false]]);
        $html = $this->page($board);
        $this->assertStringContainsString('--lototo-board-columns: 12;', $html);
        $this->assertStringNotContainsString('lototo-other-group', $html);
        $board->update(['component_settings' => [...$board->component_settings, 'other' => true]]);
        $html = $this->page($board);
        $this->assertStringContainsString('--lototo-board-columns: 30;', $html);
        $this->assertStringContainsString('lototo-page lototo-page--wide', $html);
    }

    public function test_supervisor_slots_show_their_saved_employee_and_status_and_keep_empty_indexes(): void
    {
        $board = $this->board(['board_columns' => 10, 'section_order' => ['spare', 'other']]);
        $this->slots($board, technician: 2, supervisor: 4, spare: 1, tool: 1);
        $first = $this->employee('105');
        $second = $this->employee('101');
        LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'supervisor')->where('position', 3)->update(['employee_id' => $first->id]);
        LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'supervisor')->where('position', 4)->update(['employee_id' => $second->id]);
        LototoActivity::query()->create([
            'employee_id' => $second->id, 'board' => $board->code, 'location' => 'Pump', 'activity' => 'Valve', 'risks' => ['terjepit'], 'risk_controls' => 'Lock',
            'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true, 'safe_tools' => true, 'safe_hands' => true, 'status' => 'in_progress', 'started_at' => now(),
        ]);

        $supervisor = $this->section($this->page($board), 'supervisor');

        $this->assertMatchesRegularExpression('/grid-column: 1; grid-row: 1;">\s*<div class="lototo-card lototo-empty-slot" aria-label="SUPERVISOR posisi 1 kosong"/', $supervisor);
        $this->assertMatchesRegularExpression('/grid-column: 3; grid-row: 1;">.*?LOTOTO 105, status OUT.*?>03<\/span>/s', $supervisor);
        $this->assertMatchesRegularExpression('/grid-column: 4; grid-row: 1;">.*?LOTOTO 101, status IN.*?>04<\/span>/s', $supervisor);
        $this->assertStringContainsString(route('safety.lototo.show', ['employee' => $second, 'board' => $board->id]), $supervisor);
        // Editor controls are rendered for the manager but stay hidden until Edit Mode is started.
        $this->assertMatchesRegularExpression('/data-editor-toolbar hidden/', $this->page($board));
    }

    private function board(array $layout): LototoBoard
    {
        return LototoBoard::query()->create([
            'business_unit_id' => $this->unit->id, 'division_name' => 'Render', 'code' => 'board-render-test', 'title' => 'BOARD', 'subtitle' => 'Test',
            'layout' => $layout, 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
    }

    private function slots(LototoBoard $board, int $technician, int $supervisor, int $spare, int $tool): void
    {
        foreach (compact('technician', 'supervisor', 'spare', 'tool') as $category => $count) {
            foreach (range(1, $count) as $position) {
                $lock = $category === 'spare'
                    ? SpareLock::query()->create(['board_id' => $board->id, 'code' => sprintf('SP-%02d', $position), 'is_active' => true])
                    : null;
                LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => $category, 'position' => $position, 'is_enabled' => true,
                    'spare_lock_id' => $lock?->id, 'tool_label' => $category === 'tool' ? "Tool {$position}" : null]);
            }
        }
    }

    private function employee(string $lototoNumber): Employee
    {
        return Employee::factory()->create(['business_unit_id' => $this->unit->id, 'employment_status_id' => $this->active->id, 'lototo_number' => $lototoNumber]);
    }

    private function page(LototoBoard $board): string
    {
        return $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $board))->assertOk()->getContent();
    }

    /** The markup of one board section, from its opening element to the start of the next one. */
    private function section(string $html, string $section): string
    {
        $marker = ['technician' => 'lototo-technician-grid', 'supervisor' => 'lototo-supervisor-area', 'spare' => 'lototo-spare-group', 'other' => 'lototo-other-group'][$section];
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, "Section {$section} is missing.");
        $ends = array_filter(array_map(fn ($next) => strpos($html, $next, $start + 1), ['lototo-supervisor-area', 'lototo-spare-group', 'lototo-other-group', '</section>']));

        return substr($html, $start, ($section === 'technician' ? strpos($html, '</section>', $start) : (min($ends ?: [strlen($html)]))) - $start);
    }

    private function sectionColumns(string $html): array
    {
        return collect(['technician', 'spare', 'other'])
            ->mapWithKeys(fn ($section) => [$section => preg_match('/--lototo-section-columns: (\d+);/', $this->section($html, $section), $m) ? (int) $m[1] : null])
            ->all();
    }

    private function sectionOrder(string $html): array
    {
        preg_match_all('/class="[^"]*lototo-(spare-group|other-group|supervisor-area)[^"]*"\s*style="[^"]*order: (\d+);/', $html, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn ($m) => [str_replace(['-group', '-area'], '', $m[1]) => (int) $m[2]])->sort()->all();
    }

    private function supervisorCells(string $html, array $positions): array
    {
        preg_match_all('/grid-column: (\d+); grid-row: (\d+);">.*?lototo-position-index">(\d+)</s', $this->section($html, 'supervisor'), $cells, PREG_SET_ORDER);
        $byPosition = collect($cells)->mapWithKeys(fn ($m) => [(int) $m[3] => [(int) $m[1], (int) $m[2]]]);

        return collect($positions)->mapWithKeys(fn ($position) => [$position => $byPosition->get($position)])->all();
    }
}
