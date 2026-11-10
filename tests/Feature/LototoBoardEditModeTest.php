<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\SpareLock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Board page markup for the inline Edit Mode (the save itself is covered by LototoBoardLayoutTest). */
class LototoBoardEditModeTest extends TestCase
{
    use RefreshDatabase;

    private LototoBoard $board;
    private User $admin;
    private User $staff;
    private Employee $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $unit = BusinessUnit::factory()->create(['is_active' => true]);
        $active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $unit->id, 'division_name' => 'Edit', 'code' => 'board-edit-mode', 'title' => 'BOARD', 'subtitle' => 'Test',
            'layout' => ['board_columns' => 10, 'section_order' => ['spare', 'other']],
            'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        $this->staff = User::factory()->create();
        $this->staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->technician = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'lototo_number' => '01', 'user_id' => $this->staff->id]);
        $lock = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-01', 'is_active' => true]);
        foreach ([['technician', 1, ['employee_id' => $this->technician->id]], ['technician', 2, []], ['supervisor', 1, []], ['spare', 1, ['spare_lock_id' => $lock->id]], ['tool', 1, []]] as [$category, $position, $extra]) {
            LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => $category, 'position' => $position, 'is_enabled' => true, ...$extra]);
        }
    }

    public function test_safety_managers_get_the_edit_board_button_and_hidden_editor_controls(): void
    {
        $html = $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();

        $this->assertStringContainsString('data-board-edit-start', $html);
        $this->assertStringContainsString('data-layout-url="'.route('safety.lototo.boards.layout', $this->board).'"', $html);
        $this->assertStringContainsString('data-save-url="'.route('safety.lototo.boards.layout.update', $this->board).'"', $html);
        // Toolbar and popup exist but stay hidden until Edit Board is pressed.
        $this->assertMatchesRegularExpression('/<section class="lototo-board-editor[^"]*" data-editor-toolbar hidden/', $html);
        $this->assertStringContainsString('data-slot-dialog', $html);
        // The popup holds category and slot content only: no enabled state and no position input.
        preg_match('/<form[^>]*data-slot-dialog.*?<\/form>/s', $html, $popup);
        $this->assertStringNotContainsString('Status posisi', $popup[0]);
        $this->assertStringNotContainsString('name="is_enabled"', $popup[0]);
        $this->assertStringNotContainsString('name="position"', $popup[0]);
        $this->assertStringContainsString('name="category"', $popup[0]);
        // Toolbar: Kelola Tools and Kelola Spare Lock open their popups; Technician and Supervisor slots are not added from here.
        preg_match('/<section class="lototo-board-editor.*?<\/section>/s', $html, $toolbar);
        $this->assertMatchesRegularExpression('/data-editor-manage="tools" title="[^"]*">Kelola Tools<\/button>/', $toolbar[0]);
        $this->assertMatchesRegularExpression('/data-editor-manage="spare" title="[^"]*">Kelola Spare Lock<\/button>/', $toolbar[0]);
        $this->assertStringNotContainsString('+ Tools', $toolbar[0]);
        $this->assertStringNotContainsString('+ Spare<', $toolbar[0]);
        $this->assertStringNotContainsString('data-editor-new-tool', $html);
        // The popups (closed dialogs) change board data only through their own endpoints.
        $this->assertMatchesRegularExpression('/<dialog class="lototo-manage-dialog" data-manage-tools[^>]*>.*?name="name".*?type="file" accept="image\/jpeg,image\/png,image\/webp".*?data-tool-preview.*?<\/dialog>/s', $html);
        $this->assertMatchesRegularExpression('/<dialog class="lototo-manage-dialog" data-manage-spare[^>]*>.*?data-spare-add.*?data-spare-next.*?<\/dialog>/s', $html);
        $this->assertStringContainsString('data-tools-url="'.route('safety.lototo.boards.tools.store', $this->board).'"', $html);
        $this->assertStringContainsString('data-spare-locks-url="'.route('safety.lototo.boards.spare-locks.store', $this->board).'"', $html);
        // The popup chooses a tool record instead of typing a name.
        $this->assertStringContainsString('<select name="tool_id"', $html);
        $this->assertStringNotContainsString('name="tool_label"', $html);
        $this->assertStringNotContainsString('+ Technician', $html);
        $this->assertStringNotContainsString('+ Supervisor', $html);
        $this->assertStringNotContainsString('name="columns_supervisor"', $html);
        // "Kolom per baris" for the whole board sits next to the board selector, hidden until Edit Board: one
        // dropdown, no per-band choice.
        preg_match('/<div class="lototo-editor-columns" data-editor-columns hidden>.*?<\/div>/s', $html, $columns);
        $this->assertNotEmpty($columns);
        $this->assertStringContainsString('<span>Kolom per baris:</span>', $columns[0]);
        $this->assertStringContainsString('<option value="5">5</option>', $columns[0]);
        $this->assertStringContainsString('<option value="30">30</option>', $columns[0]);
        $this->assertSame(1, substr_count($columns[0], '<select'));
        $this->assertStringNotContainsString('data-editor-column-section', $html);
        // Card templates for the live preview: one empty and one filled card per category.
        foreach (['technician', 'supervisor', 'spare', 'tool'] as $category) {
            foreach (['empty', 'filled'] as $state) {
                $this->assertStringContainsString('data-card-template="'.$category.'-'.$state.'"', $html);
            }
        }
        // Every slot tells the editor what it is; the empty Other section keeps its slots hidden for Edit Mode.
        $technicianSlot = LototoBoardSlot::query()->where('board_id', $this->board->id)->where('category', 'technician')->where('position', 1)->value('id');
        $this->assertMatchesRegularExpression('/data-slot-category="technician" data-slot-position="1" data-grid-row="1" data-grid-column="1"\s+data-slot-id="'.$technicianSlot.'"/', $html);
        $this->assertStringContainsString('data-slot-category="spare" data-slot-position="1"', $html);
        $this->assertStringContainsString('data-slot-category="supervisor" data-slot-position="1"', $html);
        $this->assertMatchesRegularExpression('/class="lototo-other-grid"[^>]*hidden data-editor-reveal/', $html);
        $this->assertMatchesRegularExpression('/<p class="lototo-empty-message"\s+data-editor-hide\s*>/', $html);
    }

    public function test_other_users_see_the_normal_board_without_editor_markup_and_cannot_use_the_endpoints(): void
    {
        $html = $this->actingAs($this->staff)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();

        foreach (['data-board-edit-start', 'data-board-editor', 'data-editor-toolbar', 'data-slot-dialog', 'lototo-slot-editor', 'data-editor-reveal', 'data-editor-hide', 'data-editor-columns', 'data-card-template', 'Edit Board'] as $editorMarkup) {
            $this->assertStringNotContainsString($editorMarkup, $html);
        }
        $this->assertStringNotContainsString('lototo-other-grid', $html);
        $this->assertStringContainsString('Belum ada data LOTOTO untuk bagian ini.', $html);
        $this->assertStringContainsString(route('safety.lototo.show', ['employee' => $this->technician, 'board' => $this->board->id]), $html);

        // Hiding the button is not the protection: the endpoints refuse the same user.
        $this->actingAs($this->staff)->getJson(route('safety.lototo.boards.layout', $this->board))->assertForbidden();
        $this->actingAs($this->staff)->putJson(route('safety.lototo.boards.layout.update', $this->board), ['title' => 'X'])->assertForbidden();
        $this->assertSame('BOARD', $this->board->fresh()->title);
    }

    public function test_opening_edit_mode_and_cancelling_writes_nothing(): void
    {
        $snapshot = fn () => [
            LototoBoard::query()->orderBy('id')->get()->map(fn ($board) => [...$board->only(['id', 'title', 'subtitle', 'layout', 'component_settings']), 'updated_at' => (string) $board->updated_at])->all(),
            LototoBoardSlot::query()->orderBy('id')->get()->map(fn ($slot) => [...$slot->only(['id', 'category', 'position', 'employee_id', 'spare_lock_id', 'tool_id', 'tool_label', 'is_enabled']), 'updated_at' => (string) $slot->updated_at])->all(),
        ];
        $before = $snapshot();

        // Edit Mode loads the layout; the draft and Cancel stay in the browser, so only reads reach the server.
        $layout = $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))->assertOk()->json();
        $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->assertOk();

        $this->assertSame($before, $snapshot());
        // What the editor sends back unchanged is accepted: slots and column counts stay as they were, and the layout
        // only gets its Supervisor-after-Spare order written out explicitly.
        $this->actingAs($this->admin)->putJson(route('safety.lototo.boards.layout.update', $this->board),
            [...collect($layout)->only(['version', 'title', 'subtitle', 'board_columns', 'section_order', 'components', 'slots'])->all(), 'apply_columns' => false])->assertOk();
        $withoutTimestamps = fn (array $rows) => array_map(fn ($row) => array_diff_key($row, ['updated_at' => 1]), $rows);
        $this->assertSame($withoutTimestamps($before[1]), $withoutTimestamps($snapshot()[1]));
        $this->assertSame($layout['columns'], \App\Http\Controllers\LototoBoardSettingsController::layoutColumns($this->board->fresh()));
        $this->assertSame(['spare', 'supervisor', 'other'], $this->board->fresh()->layout['section_order']);
    }
}
