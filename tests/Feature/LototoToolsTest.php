<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\LototoTool;
use App\Models\LototoToolActivity;
use App\Models\SpareLock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LototoToolsTest extends TestCase
{
    use RefreshDatabase;

    // A real 1x1 PNG; the GD extension is not available to generate fake images.
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private EmploymentStatus $activeStatus;

    private User $admin;

    private BusinessUnit $unit;

    private LototoBoard $board;

    private LototoBoard $otherBoard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $this->activeStatus = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        $this->unit = BusinessUnit::factory()->create(['name' => 'Half Product']);
        $this->board = $this->makeBoard($this->unit, 'HP');
        $this->otherBoard = $this->makeBoard(BusinessUnit::factory()->create(['name' => 'Tire Cord']), 'TC');
    }

    public function test_admin_creates_a_tool_with_image_in_the_first_empty_slot(): void
    {
        $this->tool($this->board, 'TL-01', 1);
        LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'tool', 'position' => 2, 'is_enabled' => false]);
        $slot = $this->toolSlot($this->board, 3);
        $this->toolSlot($this->board, 4);

        $this->actingAs($this->admin)->get(route('safety.lototo.settings', ['board' => $this->board->id, 'section' => 'tool']))
            ->assertOk()->assertDontSee('name="slot_id"', false)->assertDontSee('placeholder="Opsional"', false);
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [
            'board_id' => $this->board->id, 'code' => 'tl-16', 'name' => 'Impact Wrench', 'image' => $this->png(),
        ])->assertSessionHasNoErrors()->assertRedirect(route('safety.lototo.settings', ['board' => $this->board->id, 'section' => 'tool']))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'posisi 03'));

        // Position 1 is taken and position 2 is disabled, so the tool goes to the first empty, enabled position.
        $tool = LototoTool::query()->where('board_id', $this->board->id)->where('code', 'TL-16')->firstOrFail();
        $this->assertSame(['Impact Wrench', null, true], [$tool->name, $tool->location, $tool->is_active]);
        $this->assertStringStartsWith('lototo-tools/'.$this->board->id.'/', $tool->image_path);
        Storage::disk('local')->assertExists($tool->image_path);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'tool_id' => $tool->id, 'tool_label' => 'Impact Wrench']);

        $this->actingAs($this->admin)->get(route('safety.lototo.tools.image', $tool))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->admin)->get(route('safety.lototo.settings', ['board' => $this->board->id]))
            ->assertOk()
            ->assertSee('data-qrcode="'.e(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id])).'"', false);
    }

    public function test_full_flow_from_creating_a_tool_to_reading_its_history(): void
    {
        $slot = $this->toolSlot($this->board, 5);
        $this->toolSlot($this->otherBoard, 5);
        $worker = $this->staffOf($this->unit);

        // 1. Admin creates the tool with an image on position 05.
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [
            'board_id' => $this->board->id, 'code' => 'TL-20', 'name' => 'Torque Wrench', 'image' => $this->png(),
        ])->assertSessionHasNoErrors();
        $tool = LototoTool::query()->where('code', 'TL-20')->firstOrFail();
        $formUrl = route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id]);

        // 2. Settings lists it with its QR; the board shows it on position 05 with its image and link, as OUT.
        $this->actingAs($this->admin)->get(route('safety.lototo.settings', ['board' => $this->board->id, 'section' => 'tool']))
            ->assertOk()->assertSee('TL-20 · Torque Wrench')->assertSee('OUT · di board')->assertSee('Posisi 05');
        $this->actingAs($this->admin)->get(route('safety.lototo.settings', ['board' => $this->board->id]))
            ->assertOk()->assertSee('data-qrcode="'.e($formUrl).'"', false);
        $board = $this->actingAs($worker)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="Other posisi 5 Torque Wrench"', $board);
        $this->assertStringContainsString('href="'.e($formUrl).'"', $board);
        $this->assertStringContainsString('class="lototo-tool-image"', $board);
        $this->actingAs($worker)->get(route('safety.lototo.tools.image', $tool))->assertOk();
        $this->actingAs($worker)->get(route('safety.lototo.boards.show', $this->otherBoard))->assertNotFound();

        // 3. The worker scans the QR and does IN with a location.
        $this->actingAs($worker)->get($formUrl)->assertOk()->assertSee('data-tool-status="out"', false)->assertSee('name="location"', false);
        $this->actingAs($worker)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'in', 'location' => 'Line 2 · Extruder'])
            ->assertRedirect($formUrl)->assertSessionHas('status');
        $board = $this->actingAs($worker)->get(route('safety.lototo.boards.show', $this->board))->getContent();
        $this->assertStringContainsString('aria-label="Other posisi 5 Torque Wrench IN di Line 2 · Extruder"', $board);
        $this->actingAs($this->admin)->get(route('safety.lototo.settings', ['board' => $this->board->id, 'section' => 'tool']))->assertSee('IN · Line 2 · Extruder')->assertSee('Sedang IN');

        // 4. The worker does OUT; the tool is back on the board.
        $open = $tool->openActivity()->firstOrFail();
        $this->actingAs($worker)->get($formUrl)->assertSee('data-tool-status="in"', false)->assertSee('OUT · Kembalikan ke board');
        $this->actingAs($worker)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'out', 'activity_id' => $open->id])
            ->assertRedirect($formUrl)->assertSessionHasNoErrors();
        $board = $this->actingAs($worker)->get(route('safety.lototo.boards.show', $this->board))->getContent();
        $this->assertStringContainsString('aria-label="Other posisi 5 Torque Wrench"', $board);
        $this->assertStringNotContainsString('lototo-tool-status', $board);

        // 5. History shows who did IN and OUT, where and when; the tool can no longer be deleted, only deactivated.
        $this->actingAs($worker)->get($formUrl)->assertOk()
            ->assertSee('Line 2 · Extruder')->assertSee($worker->name)->assertSee($open->fresh()->ended_at->setTimezone('Asia/Jakarta')->format('d M Y H:i'));
        $this->assertDatabaseHas('lototo_tool_activities', ['id' => $open->id, 'status' => 'finished', 'started_by' => $worker->id, 'ended_by' => $worker->id, 'board_id' => $this->board->id]);
        $this->actingAs($this->admin)->delete(route('safety.lototo.tools.destroy', $tool), ['board_id' => $this->board->id])->assertSessionHasErrors('tool');
        $this->actingAs($this->admin)->get(route('safety.lototo.settings', ['board' => $this->board->id, 'section' => 'tool']))->assertSee('Memiliki riwayat')->assertDontSee('data-tool-code="TL-20"', false);
    }

    public function test_tool_validation_and_permissions(): void
    {
        LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-01', 'name' => 'Kunci']);
        $foreignSlot = $this->toolSlot($this->otherBoard, 1);
        $payload = ['board_id' => $this->board->id, 'name' => 'Obeng'];

        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [...$payload, 'code' => 'TL-01'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [...$payload, 'board_id' => $this->otherBoard->id, 'code' => 'TL-01'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [...$payload, 'code' => 'TL 02'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [...$payload, 'code' => 'TL-03', 'name' => ''])->assertSessionHasErrors('name');
        // Real uploads (not UploadedFile::fake(), which trusts the file name) so the content check is exercised.
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [...$payload, 'code' => 'TL-05', 'image' => $this->upload('tool.png', 'not an image')])->assertSessionHasErrors('image');
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [...$payload, 'code' => 'TL-06', 'image' => $this->png(2100 * 1024)])->assertSessionHasErrors('image');
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [...$payload, 'code' => 'TL-07', 'image' => $this->upload('tool.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertSessionHasErrors('image');
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), [...$payload, 'code' => 'TL-08', 'image' => $this->upload('tool.php.png', '<?php echo 1;')])->assertSessionHasErrors('image');

        $this->assertSame(['TL-01'], LototoTool::query()->where('board_id', $this->board->id)->pluck('code')->all());
        $this->assertSame([], Storage::disk('local')->allFiles(), 'Rejected uploads must not leave files behind.');
        // Only the accepted TL-01 of the other board was placed, on that board's own empty position; this board got no position.
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $foreignSlot->id, 'tool_id' => LototoTool::query()->where('board_id', $this->otherBoard->id)->value('id')]);
        $this->assertSame(0, LototoBoardSlot::query()->where('board_id', $this->board->id)->count());

        $staff = $this->staffOf($this->unit);
        $tool = LototoTool::query()->where('board_id', $this->board->id)->firstOrFail();
        $this->actingAs($staff)->post(route('safety.lototo.tools.store'), [...$payload, 'code' => 'TL-09'])->assertForbidden();
        $this->actingAs($staff)->patch(route('safety.lototo.tools.update', $tool), [...$payload, 'code' => 'TL-01', 'is_active' => 1])->assertForbidden();
        $this->actingAs($staff)->delete(route('safety.lototo.tools.destroy', $tool), ['board_id' => $this->board->id])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('safety.lototo.tools.update', $tool), ['board_id' => $this->otherBoard->id, 'code' => 'X', 'name' => 'X', 'is_active' => 1])->assertNotFound();
        $this->actingAs($this->admin)->delete(route('safety.lototo.tools.destroy', $tool), ['board_id' => $this->otherBoard->id])->assertNotFound();
        $this->assertDatabaseHas('lototo_tools', ['id' => $tool->id, 'code' => 'TL-01', 'name' => 'Kunci']);
    }

    public function test_updating_a_tool_replaces_or_removes_its_image_without_orphan_files(): void
    {
        $slot = $this->toolSlot($this->board, 1);
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), ['board_id' => $this->board->id, 'code' => 'TL-01', 'name' => 'Kunci', 'image' => $this->png()]);
        $tool = LototoTool::query()->firstOrFail();
        $firstImage = $tool->image_path;

        $this->actingAs($this->admin)->patch(route('safety.lototo.tools.update', $tool), [
            'board_id' => $this->board->id, 'code' => 'tl-01a', 'name' => 'Kunci Pas', 'location' => 'Rak B', 'is_active' => 1, 'image' => $this->png(),
        ])->assertSessionHasNoErrors();
        $tool->refresh();
        $this->assertSame(['TL-01A', 'Kunci Pas', 'Rak B'], [$tool->code, $tool->name, $tool->location]);
        $this->assertNotSame($firstImage, $tool->image_path);
        Storage::disk('local')->assertMissing($firstImage);
        Storage::disk('local')->assertExists($tool->image_path);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'tool_id' => $tool->id, 'tool_label' => 'Kunci Pas', 'position' => 1]);

        // Deactivating keeps the image: the tool still exists and may be reactivated.
        $this->actingAs($this->admin)->patch(route('safety.lototo.tools.update', $tool), ['board_id' => $this->board->id, 'code' => 'TL-01A', 'name' => 'Kunci Pas', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($tool->fresh()->is_active);
        Storage::disk('local')->assertExists($tool->image_path);

        $this->actingAs($this->admin)->patch(route('safety.lototo.tools.update', $tool), ['board_id' => $this->board->id, 'code' => 'TL-01A', 'name' => 'Kunci Pas', 'is_active' => 1, 'remove_image' => 1])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($tool->image_path);
        $this->assertNull($tool->fresh()->image_path);
        $this->assertSame([], Storage::disk('local')->allFiles());

        // A failed update (duplicate code) keeps the old data and leaves no new file.
        LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-02', 'name' => 'Obeng']);
        $this->actingAs($this->admin)->patch(route('safety.lototo.tools.update', $tool), ['board_id' => $this->board->id, 'code' => 'TL-02', 'name' => 'X', 'is_active' => 1, 'image' => $this->png()])->assertSessionHasErrors('code');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_unused_tool_can_be_deleted_but_used_or_in_tools_cannot(): void
    {
        $unused = $this->tool($this->board, 'TL-01', 1);
        $this->actingAs($this->admin)->patch(route('safety.lototo.tools.update', $unused), ['board_id' => $this->board->id, 'code' => 'TL-01', 'name' => 'Tool 1', 'is_active' => 1, 'image' => $this->png()]);
        $unusedImage = $unused->fresh()->image_path;
        $used = $this->tool($this->board, 'TL-02', 2);
        $history = LototoToolActivity::query()->create(['tool_id' => $used->id, 'board_id' => $this->board->id, 'location' => 'Line A', 'status' => 'finished', 'started_by' => $this->admin->id, 'ended_by' => $this->admin->id, 'started_at' => now()->subHour(), 'ended_at' => now()]);
        $inUse = $this->tool($this->board, 'TL-03', 3);
        $open = LototoToolActivity::query()->create(['tool_id' => $inUse->id, 'board_id' => $this->board->id, 'location' => 'Line B', 'status' => 'in_progress', 'started_by' => $this->admin->id, 'started_at' => now()]);

        $this->actingAs($this->admin)->delete(route('safety.lototo.tools.destroy', $unused), ['board_id' => $this->board->id])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lototo_tools', ['id' => $unused->id]);
        Storage::disk('local')->assertMissing($unusedImage);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $this->board->id, 'category' => 'tool', 'position' => 1, 'tool_id' => null, 'tool_label' => null]);

        foreach ([$used, $inUse] as $tool) {
            $this->actingAs($this->admin)->delete(route('safety.lototo.tools.destroy', $tool), ['board_id' => $this->board->id])->assertSessionHasErrors('tool');
            $this->assertDatabaseHas('lototo_tools', ['id' => $tool->id]);
        }
        $this->actingAs($this->admin)->patch(route('safety.lototo.tools.update', $inUse), ['board_id' => $this->board->id, 'code' => 'TL-03', 'name' => 'Tool 3', 'is_active' => 0])->assertSessionHasErrors('is_active');
        $this->assertTrue($inUse->fresh()->is_active);
        $this->assertDatabaseHas('lototo_tool_activities', ['id' => $history->id]);
        $this->assertDatabaseHas('lototo_tool_activities', ['id' => $open->id, 'status' => 'in_progress']);
    }

    public function test_in_and_out_follow_the_agreed_rules_and_keep_history(): void
    {
        $tool = $this->tool($this->board, 'TL-01', 1);
        $starter = $this->staffOf($this->unit);
        $colleague = $this->staffOf($this->unit);
        $formUrl = route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id]);

        $this->actingAs($starter)->get($formUrl)->assertOk()->assertSee('data-tool-status="out"', false)->assertSee('IN · Gunakan Tools')->assertSee($this->board->title)->assertDontSee('lototo_number');
        $this->actingAs($starter)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'in', 'location' => ''])->assertSessionHasErrors('location');
        $this->actingAs($starter)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'in', 'location' => 'Line 3'])
            ->assertSessionHasNoErrors()->assertRedirect($formUrl);
        $open = LototoToolActivity::query()->where('tool_id', $tool->id)->where('status', 'in_progress')->firstOrFail();
        $this->assertSame([$starter->id, 'Line 3', $this->board->id], [$open->started_by, $open->location, $open->board_id]);

        // A second IN while the tool is IN is refused, whoever sends it.
        $this->actingAs($colleague)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'in', 'location' => 'Line 4'])->assertSessionHasErrors('action');
        $this->assertSame(1, LototoToolActivity::query()->where('tool_id', $tool->id)->count());

        // OUT: not by a colleague, not with a stale activity id, yes by the user who did IN.
        $this->actingAs($colleague)->get($formUrl)->assertOk()->assertSee('data-tool-status="in"', false)->assertDontSee('OUT · Kembalikan ke board');
        $this->actingAs($colleague)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'out', 'activity_id' => $open->id])->assertForbidden();
        $this->actingAs($starter)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'out', 'activity_id' => $open->id + 999])->assertSessionHasErrors('action');
        $this->assertSame('in_progress', $open->fresh()->status);
        $this->actingAs($starter)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'out', 'activity_id' => $open->id])->assertSessionHasNoErrors();
        $open->refresh();
        $this->assertSame(['finished', $starter->id], [$open->status, $open->ended_by]);
        $this->assertNotNull($open->ended_at);

        // OUT again on a finished activity is refused; the manager may close someone else's IN.
        $this->actingAs($starter)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'out', 'activity_id' => $open->id])->assertSessionHasErrors('action');
        $this->actingAs($colleague)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'in', 'location' => 'Line 5'])->assertSessionHasNoErrors();
        $second = LototoToolActivity::query()->where('tool_id', $tool->id)->where('status', 'in_progress')->firstOrFail();
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'out', 'activity_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame($this->admin->id, $second->fresh()->ended_by);

        // History is kept and listed.
        $this->assertSame(2, LototoToolActivity::query()->where('tool_id', $tool->id)->count());
        $this->actingAs($starter)->get($formUrl)->assertOk()->assertSee('Line 3')->assertSee('Line 5');
    }

    public function test_inactive_tool_or_board_cannot_go_in_but_an_open_activity_can_still_go_out(): void
    {
        $tool = $this->tool($this->board, 'TL-01', 1);
        $open = LototoToolActivity::query()->create(['tool_id' => $tool->id, 'board_id' => $this->board->id, 'location' => 'Line A', 'status' => 'in_progress', 'started_by' => $this->admin->id, 'started_at' => now()]);
        $inactive = $this->tool($this->board, 'TL-02', 2);
        $inactive->update(['is_active' => false]);

        $this->actingAs($this->admin)->post(route('safety.lototo.tools.form.store', $inactive), ['board_id' => $this->board->id, 'action' => 'in', 'location' => 'Line A'])->assertSessionHasErrors('action');
        $this->board->update(['is_active' => false]);
        $free = $this->tool($this->board, 'TL-03', 3);
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.form.store', $free), ['board_id' => $this->board->id, 'action' => 'in', 'location' => 'Line A'])->assertSessionHasErrors('action');
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'out', 'activity_id' => $open->id])->assertSessionHasNoErrors();
        $this->assertSame(0, LototoToolActivity::query()->where('status', 'in_progress')->count());
    }

    public function test_the_database_rejects_a_second_open_activity_even_past_the_form_checks(): void
    {
        $tool = $this->tool($this->board, 'TL-01', 1);
        LototoToolActivity::query()->create(['tool_id' => $tool->id, 'board_id' => $this->board->id, 'location' => 'Line A', 'status' => 'in_progress', 'started_by' => $this->admin->id, 'started_at' => now()]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        LototoToolActivity::query()->create(['tool_id' => $tool->id, 'board_id' => $this->board->id, 'location' => 'Line B', 'status' => 'in_progress', 'started_by' => $this->admin->id, 'started_at' => now()]);
    }

    public function test_an_in_that_loses_a_race_gets_a_clear_message_and_leaves_one_open_activity(): void
    {
        $tool = $this->tool($this->board, 'TL-01', 1);
        $winner = $this->staffOf($this->unit);
        $raced = false;
        // Another user's IN lands between this request's status check and its insert.
        LototoToolActivity::creating(function () use ($tool, $winner, &$raced) {
            if (! $raced) {
                $raced = true;
                LototoToolActivity::query()->create(['tool_id' => $tool->id, 'board_id' => $this->board->id, 'location' => 'Line A', 'status' => 'in_progress', 'started_by' => $winner->id, 'started_at' => now()]);
            }
        });

        $this->actingAs($this->admin)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'in', 'location' => 'Line B'])
            ->assertSessionHasErrors(['action' => 'Tools TL-01 baru saja di-IN oleh pengguna lain. Muat ulang halaman untuk melihat status terkini.']);
        $this->assertSame(0, LototoToolActivity::query()->where('location', 'Line B')->count());
    }

    public function test_a_tool_is_only_reachable_through_its_own_board(): void
    {
        $tool = $this->tool($this->board, 'TL-01', 1);
        $this->tool($this->otherBoard, 'TL-01', 1);
        $outsider = $this->staffOf(BusinessUnit::factory()->create(['name' => 'Elsewhere']));

        $this->actingAs($this->admin)->get(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->otherBoard->id]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('safety.lototo.tools.form', ['tool' => $tool->id]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->code]))->assertOk();
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->otherBoard->id, 'action' => 'in', 'location' => 'X'])->assertNotFound();
        $this->actingAs($outsider)->get(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id]))->assertNotFound();
        $this->actingAs($outsider)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'in', 'location' => 'X'])->assertNotFound();
        $this->assertSame(0, LototoToolActivity::query()->count());

        $tool->update(['image_path' => Storage::disk('local')->putFile('lototo-tools/'.$this->board->id, $this->png())]);
        $this->actingAs($outsider)->get(route('safety.lototo.tools.image', $tool))->assertNotFound();
        $this->actingAs($this->staffOf($this->unit))->get(route('safety.lototo.tools.image', $tool))->assertOk();
        auth()->logout();
        $this->get(route('safety.lototo.tools.image', $tool))->assertRedirect();
    }

    public function test_a_new_tool_gets_a_new_last_position_when_every_position_is_taken(): void
    {
        $this->tool($this->board, 'TL-01', 1);
        LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'tool', 'position' => 2, 'tool_label' => 'Legacy label', 'is_enabled' => true]);

        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), ['board_id' => $this->board->id, 'code' => 'TL-02', 'name' => 'Obeng'])->assertSessionHasNoErrors();

        $tool = LototoTool::query()->where('board_id', $this->board->id)->where('code', 'TL-02')->firstOrFail();
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $this->board->id, 'category' => 'tool', 'position' => 3, 'tool_id' => $tool->id, 'tool_label' => 'Obeng', 'is_enabled' => true]);
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $this->board->id, 'position' => 2, 'tool_label' => 'Legacy label', 'tool_id' => null]);
        $this->assertStringContainsString('aria-label="Other posisi 3 Obeng"', $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->getContent());
    }

    public function test_the_same_tool_code_on_two_boards_always_resolves_to_the_tool_of_the_given_board(): void
    {
        $mine = $this->tool($this->board, 'TL-01', 1);
        $theirs = $this->tool($this->otherBoard, 'TL-01', 1);

        // Each board's QR and board link carry that board's own tool.
        $this->actingAs($this->admin)->get(route('safety.lototo.settings', ['board' => $this->otherBoard->id]))->assertOk()
            ->assertSee('data-qrcode="'.e(route('safety.lototo.tools.form', ['tool' => $theirs->id, 'board' => $this->otherBoard->id])).'"', false)
            ->assertDontSee(e(route('safety.lototo.tools.form', ['tool' => $mine->id, 'board' => $this->otherBoard->id])), false);
        $this->assertStringContainsString(
            'href="'.e(route('safety.lototo.tools.form', ['tool' => $theirs->id, 'board' => $this->otherBoard->id])).'"',
            $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->otherBoard))->getContent()
        );

        // IN on the other board's TL-01 changes only that tool; swapping the board on either tool is refused.
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.form.store', $theirs), ['board_id' => $this->otherBoard->id, 'action' => 'in', 'location' => 'Line 9'])->assertSessionHasNoErrors();
        $this->assertNotNull($theirs->openActivity()->first());
        $this->assertNull($mine->openActivity()->first());
        $this->actingAs($this->admin)->get(route('safety.lototo.tools.form', ['tool' => $theirs->id, 'board' => $this->board->id]))->assertNotFound();
        $this->actingAs($this->admin)->post(route('safety.lototo.tools.form.store', $mine), ['board_id' => $this->otherBoard->id, 'action' => 'in', 'location' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->patch(route('safety.lototo.tools.update', $theirs), ['board_id' => $this->board->id, 'code' => 'TL-01', 'name' => 'X', 'is_active' => 1])->assertNotFound();
        $this->assertSame(['Tool 1', 'Tool 1'], [$mine->fresh()->name, $theirs->fresh()->name]);
    }

    public function test_a_code_taken_by_a_concurrent_request_is_reported_as_a_validation_error(): void
    {
        // Simulates a second manager saving the same code between this request's unique check and its insert.
        LototoTool::creating(function (LototoTool $tool) {
            if ($tool->code === 'TL-30' && ! LototoTool::query()->where('board_id', $tool->board_id)->where('code', 'TL-30')->exists()) {
                LototoTool::withoutEvents(fn () => LototoTool::query()->create(['board_id' => $tool->board_id, 'code' => 'TL-30', 'name' => 'Pemenang']));
            }
        });

        $this->actingAs($this->admin)->post(route('safety.lototo.tools.store'), ['board_id' => $this->board->id, 'code' => 'TL-30', 'name' => 'Kalah', 'image' => $this->png()])
            ->assertSessionHasErrors(['code' => 'Kode Tools sudah digunakan pada board ini.']);

        // The simulated winner shares this request's transaction here, so only the loser's leftovers can be checked.
        $this->assertFalse(LototoTool::query()->where('name', 'Kalah')->exists());
        $this->assertSame(0, LototoBoardSlot::query()->where('board_id', $this->board->id)->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_activity_times_are_stored_in_utc_and_shown_in_local_time(): void
    {
        $tool = $this->tool($this->board, 'TL-01', 1);
        $activity = LototoToolActivity::query()->create(['tool_id' => $tool->id, 'board_id' => $this->board->id, 'location' => 'Line A', 'status' => 'finished', 'started_by' => $this->admin->id, 'ended_by' => $this->admin->id, 'started_at' => '2026-10-29 03:05:00', 'ended_at' => '2026-10-29 17:30:00']);

        $this->actingAs($this->admin)->get(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id]))
            ->assertOk()->assertSee('29 Oct 2026 10:05')->assertSee('30 Oct 2026 00:30')->assertDontSee('29 Oct 2026 03:05');
        $this->assertSame('2026-10-29 03:05:00', $activity->fresh()->getRawOriginal('started_at'));
    }

    public function test_board_buttons_are_named_settings_and_qr_code_and_keep_their_pages(): void
    {
        $html = $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('safety.lototo.boards.edit', $this->board), '#').'" class="lototo-board-action">\s*<svg[^>]*>.*?</svg>\s*Settings\s*</a>#s', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('safety.lototo.settings', ['board' => $this->board->id]), '#').'" class="lototo-board-action">\s*<svg[^>]*>.*?</svg>\s*QR Code\s*</a>#s', $html);
        $this->assertStringNotContainsString('Edit Board', $html);

        $staffHtml = $this->actingAs($this->staffOf($this->unit))->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();
        $this->assertStringNotContainsString('lototo-board-action"', $staffHtml);
    }

    public function test_board_shows_tool_image_status_and_keeps_old_slots(): void
    {
        $withImage = $this->tool($this->board, 'TL-01', 1);
        $withImage->update(['image_path' => Storage::disk('local')->putFile('lototo-tools/'.$this->board->id, $this->png())]);
        $inUse = $this->tool($this->board, 'TL-02', 2);
        LototoToolActivity::query()->create(['tool_id' => $inUse->id, 'board_id' => $this->board->id, 'location' => 'Line 7', 'status' => 'in_progress', 'started_by' => $this->admin->id, 'started_at' => now()]);
        LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'tool', 'position' => 3, 'tool_label' => 'Legacy label', 'tool_column_span' => 2, 'is_enabled' => true]);
        $this->toolSlot($this->board, 4);
        $this->tool($this->otherBoard, 'TL-09', 1);

        $html = $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();

        $this->assertStringContainsString('class="lototo-tool-image" src="'.route('safety.lototo.tools.image', $withImage), $html);
        $this->assertStringContainsString('href="'.e(route('safety.lototo.tools.form', ['tool' => $withImage->id, 'board' => $this->board->id])).'"', $html);
        $this->assertStringContainsString('aria-label="Other posisi 2 Tool 2 IN di Line 7"', $html);
        $this->assertStringContainsString('<span class="lototo-tool-status">IN</span>', $html);
        $this->assertStringContainsString('aria-label="Other posisi 3 Legacy label"', $html);
        $this->assertStringContainsString('grid-column: 3 / span 2;', $html);
        $this->assertStringContainsString('aria-label="Other posisi 4 kosong"', $html);
        $this->assertStringNotContainsString('TL-09', $html);
        $this->assertStringNotContainsString('Tool 9', $html);
    }

    public function test_tools_do_not_change_spare_lock_or_employee_qr_behaviour(): void
    {
        $lock = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SPH-01', 'is_active' => true]);
        $this->tool($this->board, 'TL-01', 1);

        $this->actingAs($this->admin)->get(route('safety.lototo.form', ['spare_lock' => 'SPH-01', 'board' => $this->board->id]))
            ->assertOk()->assertSee('name="spare_lock_id" value="'.$lock->id.'"', false);
        $this->actingAs($this->admin)->get(route('safety.lototo.form', ['spare_lock' => 'SPH-01', 'board' => $this->otherBoard->id]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('safety.lototo.settings', ['board' => $this->board->id]))
            ->assertOk()
            ->assertSee('data-qrcode="'.e(route('safety.lototo.form', ['spare_lock' => 'SPH-01', 'board' => $this->board->id])).'"', false);
    }

    private function png(int $paddedSize = 0): UploadedFile
    {
        $bytes = base64_decode(self::PNG);
        if ($paddedSize > strlen($bytes)) {
            $bytes .= str_repeat("\0", $paddedSize - strlen($bytes));
        }

        return $this->upload('tool.png', $bytes);
    }

    private function upload(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tool');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function makeBoard(BusinessUnit $unit, string $suffix): LototoBoard
    {
        return LototoBoard::query()->create([
            'business_unit_id' => $unit->id,
            'division_name' => 'Maintenance',
            'code' => 'board-test-'.strtolower($suffix),
            'title' => 'MAINTENANCE · '.$unit->name,
            'subtitle' => 'Daftar LOTOTO karyawan dan spare',
            'layout' => ['board_columns' => 20, 'section_order' => ['spare', 'other']],
            'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true],
            'is_active' => true,
        ]);
    }

    private function toolSlot(LototoBoard $board, int $position): LototoBoardSlot
    {
        return LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'tool', 'position' => $position, 'is_enabled' => true]);
    }

    private function tool(LototoBoard $board, string $code, int $position): LototoTool
    {
        $tool = LototoTool::query()->create(['board_id' => $board->id, 'code' => $code, 'name' => 'Tool '.$position]);
        LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'tool', 'position' => $position, 'tool_id' => $tool->id, 'tool_label' => $tool->name, 'is_enabled' => true]);

        return $tool;
    }

    private function staffOf(BusinessUnit $unit): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::MaintenanceStaff->value);
        Employee::factory()->create(['user_id' => $user->id, 'business_unit_id' => $unit->id, 'employment_status_id' => $this->activeStatus->id]);

        return $user;
    }
}
