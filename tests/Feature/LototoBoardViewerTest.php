<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Http\Middleware\AllowLototoQrForm;
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
use Tests\TestCase;

/**
 * The board every viewer sees, the red LOTOTO number marker, the Spare Lock QR flow and slots placed by the legacy
 * fallback in Edit Mode.
 */
class LototoBoardViewerTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $unit;
    private EmploymentStatus $active;
    private LototoBoard $board;
    private User $admin;
    private Employee $own;
    private SpareLock $spare;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->unit = BusinessUnit::factory()->create(['name' => 'Tire Cord', 'is_active' => true]);
        $this->active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $this->unit->id, 'division_name' => 'Maintenance', 'code' => 'mtn_tr', 'title' => 'MAINTENANCE · TIRE CORD', 'subtitle' => 'x',
            'layout' => ['board_columns' => 20], 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        foreach (['07', '08', '09'] as $index => $number) {
            $employee = Employee::factory()->create(['business_unit_id' => $this->unit->id, 'employment_status_id' => $this->active->id, 'lototo_number' => $number]);
            $this->own ??= $employee;
            LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'technician', 'position' => $index + 1, 'is_enabled' => true, 'employee_id' => $employee->id]);
        }
        $supervisor = Employee::factory()->create(['business_unit_id' => $this->unit->id, 'employment_status_id' => $this->active->id, 'lototo_number' => '101']);
        LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'supervisor', 'position' => 1, 'is_enabled' => true, 'employee_id' => $supervisor->id]);
        $this->spare = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-TC-01', 'is_active' => true]);
        LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'spare', 'position' => 1, 'is_enabled' => true, 'spare_lock_id' => $this->spare->id]);
        LototoSlotGrid::sync($this->board);
    }

    public function test_a_signed_in_user_sees_the_same_board_as_the_guest_viewer(): void
    {
        $guest = User::factory()->create();
        $guest->assignRole(RoleName::Guest->value);
        // Maintenance staff may only see their own employee record elsewhere in the application.
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->own->update(['user_id' => $staff->id]);
        $spvUser = User::factory()->create();
        $spvUser->assignRole(RoleName::MaintenanceSupervisor->value);
        Employee::factory()->create(['business_unit_id' => $this->unit->id, 'employment_status_id' => $this->active->id, 'user_id' => $spvUser->id]);

        $board = fn (User $user) => $this->boardSection($this->actingAs($user)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent());
        $guestBoard = $board($guest);
        // Every lock of the board is drawn for the guest.
        foreach (['07', '08', '09', '101'] as $number) {
            $this->assertMatchesRegularExpression('/aria-label="[^"]+ LOTOTO '.$number.', status OUT"/', $guestBoard);
        }
        $this->assertSame($guestBoard, $board($staff));
        $this->assertSame($guestBoard, $board($spvUser));

        // Management stays with Manage Safety: no editor, Edit Board or Manage Board for staff.
        $staffPage = $this->actingAs($staff)->get(route('safety.lototo.boards.show', $this->board))->getContent();
        foreach (['data-board-edit-start', 'data-editor-toolbar', route('safety.lototo.boards.index', ['board' => $this->board->id])] as $managerOnly) {
            $this->assertStringNotContainsString($managerOnly, $staffPage);
        }
        $this->actingAs($staff)->getJson(route('safety.lototo.boards.layout', $this->board))->assertForbidden();
        // Each employee's activity page keeps its own visibility rule.
        $other = LototoBoardSlot::query()->where('board_id', $this->board->id)->where('category', 'technician')->where('position', 2)->value('employee_id');
        $this->actingAs($staff)->get(route('safety.lototo.show', ['employee' => $other, 'board' => $this->board->id]))->assertNotFound();
        // The administrator keeps the editor.
        $this->assertStringContainsString('data-board-edit-start', $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->getContent());
    }

    public function test_the_lototo_number_is_marked_red_on_the_board_and_the_form(): void
    {
        $page = $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->getContent();
        $this->assertMatchesRegularExpression('/<span class="lototo-number">07<\/span>/', $page);
        $css = file_get_contents(resource_path('css/lototo.css'));
        $this->assertMatchesRegularExpression('/\.lototo-card \.lototo-lock-plate \.lototo-number \{[^}]*background: #dc2626;[^}]*color: #fff;/s', $css);

        $form = $this->get(AllowLototoQrForm::url('safety.lototo.form', ['lototo_number' => '07', 'employee_id' => $this->own->id, 'board' => $this->board->id]))->getContent();
        $this->assertMatchesRegularExpression('/id="lototo_number"[^>]*value="07"[^>]*class="[^"]*bg-red-600[^"]*text-white/s', $form);
    }

    public function test_the_spare_lock_qr_opens_in_when_free_and_the_running_activity_when_in_use(): void
    {
        // The QR the download draws for this lock (_print-designs records) is the signed form URL of its code.
        $page = $this->actingAs($this->admin)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))->getContent();
        preg_match('#<script type="application/json" data-print-records>(.*?)</script>#s', $page, $m);
        $record = collect(json_decode(html_entity_decode($m[1]), true))->firstWhere('kind', 'spare');
        $qr = AllowLototoQrForm::url('safety.lototo.form', ['spare_lock' => 'SP-TC-01', 'board' => $this->board->id]);
        $this->assertSame($qr, $record['qr']);
        $this->assertSame('SP-TC-01', $record['number']);
        auth()->logout();

        // Free: scanned without a login it asks for one, then opens the IN form of this lock (Safety managers).
        $this->get($qr)->assertRedirect(route('guest-login'));
        // After the login it comes back to this same signed QR URL.
        parse_str((string) parse_url(session('url.intended'), PHP_URL_QUERY), $intended);
        parse_str((string) parse_url($qr, PHP_URL_QUERY), $expected);
        ksort($intended);
        ksort($expected);
        $this->assertSame([parse_url($qr, PHP_URL_PATH), $expected], [parse_url(session('url.intended'), PHP_URL_PATH), $intended]);
        // ...and that saved URL, opened once signed in, still carries a valid QR signature.
        $this->actingAs($this->admin)->get(session('url.intended'))->assertOk()->assertSee('Gembok spare SP-TC-01 tersedia');
        auth()->logout();
        $this->actingAs($this->admin)->get($qr)->assertOk()
            ->assertSee('Gembok spare SP-TC-01 tersedia')
            ->assertSee('name="spare_lock_id" value="'.$this->spare->id.'"', false);
        // A signed-in technician cannot start a free spare lock.
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->own->update(['user_id' => $staff->id]);
        $this->actingAs($staff)->get($qr)->assertForbidden();
        auth()->logout();

        // In use by LOTOTO 07: the QR opens the OUT of that running activity, for its holder only.
        $activity = LototoActivity::query()->create(['employee_id' => $this->own->id, 'board' => 'mtn_tr', 'spare_lock_id' => $this->spare->id, 'location' => 'Line 9',
            'activity' => 'Handover', 'risks' => ['x'], 'risk_controls' => 'c', 'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true,
            'safe_tools' => true, 'safe_hands' => true, 'status' => 'in_progress', 'started_at' => now('UTC'),
            'out_details' => ['spare_lock_code' => 'SP-TC-01', 'spare_started_at' => now('UTC')->toISOString(), 'spare_only' => true]]);
        $this->spare->update(['current_activity_id' => $activity->id]);
        $out = $this->get($qr)->assertOk()->getContent();
        $this->assertStringContainsString('value="07"', $out);
        $this->assertStringContainsString('OUT — aktivitas mulai', $out);
        $this->actingAs($staff)->get($qr)->assertOk()->assertSee('OUT — aktivitas mulai');
        $colleague = User::factory()->create();
        $colleague->assignRole(RoleName::MaintenanceStaff->value);
        Employee::query()->where('lototo_number', '08')->update(['user_id' => $colleague->id]);
        $this->actingAs($colleague)->get($qr)->assertForbidden();
        $this->actingAs($this->admin)->get($qr)->assertOk()->assertSee('OUT — aktivitas mulai');
    }

    public function test_tools_slot_11_of_mtn_tr_is_managed_like_any_other_slot(): void
    {
        $tools = [];
        foreach (range(1, 15) as $position) {
            $tool = $position === 12 ? LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-12', 'name' => 'Kunci', 'is_active' => true]) : null;
            $tools[$position] = LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'tool', 'position' => $position, 'is_enabled' => true,
                'tool_id' => $tool?->id, 'tool_label' => $tool?->name])->id;
        }
        // Slots saved before cells existed (the legacy fallback places them by number).
        DB::table('lototo_board_slots')->where('board_id', $this->board->id)->where('category', 'tool')->update(['grid_row' => null, 'grid_column' => null]);

        // Opening Edit Mode writes nothing (the editor works with these slots by number).
        $rows = fn () => DB::table('lototo_board_slots')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $before = $rows();
        $layout = $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))->assertOk()->json();
        $this->assertFalse($layout['coordinates']);
        $this->assertSame($before, $rows());

        // Tools 11 (empty) is removed like any slot; the save gives every slot its cell, so the slots after it close
        // up and no empty position 11 is drawn again.
        $draft = [...collect($layout)->only(['version', 'title', 'subtitle', 'board_columns', 'section_order', 'components', 'slots'])->all(), 'apply_columns' => false];
        $draft['removed'] = [$tools[11]];
        $draft['slots']['tool'] = array_values(array_filter($draft['slots']['tool'], fn ($slot) => $slot['id'] !== $tools[11]));
        $this->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)->assertOk();
        $this->assertNull(LototoBoardSlot::query()->find($tools[11]));
        $this->assertSame([1, 11], [LototoBoardSlot::query()->find($tools[12])->grid_row, LototoBoardSlot::query()->find($tools[12])->grid_column]);
        $this->assertSame(0, LototoBoardSlot::query()->where('board_id', $this->board->id)->where(fn ($q) => $q->whereNull('grid_row')->orWhereNull('grid_column'))->count());
        $this->assertTrue($this->getJson(route('safety.lototo.boards.layout', $this->board))->json('coordinates'));
        $page = $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->getContent();
        $this->assertStringNotContainsString('fallback legacy', $page);
        $this->assertStringNotContainsString('Other posisi 11 kosong', $page);
        // The tool record of Tools 12 is untouched.
        $this->assertSame('TL-12', LototoTool::query()->find(LototoBoardSlot::query()->find($tools[12])->tool_id)->code);

        // A Tools slot whose tool is IN stays protected, and so does its tool record.
        LototoToolActivity::query()->create(['tool_id' => LototoBoardSlot::query()->find($tools[12])->tool_id, 'board_id' => $this->board->id, 'location' => 'L',
            'status' => 'in_progress', 'started_by' => $this->admin->id, 'started_at' => now('UTC')]);
        $layout = $this->getJson(route('safety.lototo.boards.layout', $this->board))->json();
        $draft = [...collect($layout)->only(['version', 'title', 'subtitle', 'board_columns', 'section_order', 'components', 'slots'])->all(), 'apply_columns' => false];
        $draft['removed'] = [$tools[12]];
        $draft['slots']['tool'] = array_values(array_filter($draft['slots']['tool'], fn ($slot) => $slot['id'] !== $tools[12]));
        $this->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)->assertUnprocessable()
            ->assertJsonFragment(['slots' => ['Other / Tools 12 memiliki Tools yang sedang IN dan tidak dapat dihapus.']]);
        $this->assertNotNull(LototoBoardSlot::query()->find($tools[12]));
    }

    /** The board itself (<section class="lototo-board ...">), without the page around it. */
    private function boardSection(string $html): string
    {
        $start = strpos($html, '<section', strpos($html, 'class="lototo-board ') - 40);
        $end = strrpos($html, '</section>');

        return preg_replace('/name="_token" value="[^"]+"/', '', substr($html, $start, $end - $start));
    }
}
