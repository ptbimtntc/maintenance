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
use Illuminate\Support\Carbon;
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

    public function test_admin_creates_a_tool_with_image_without_placing_it_on_a_slot(): void
    {
        $this->tool($this->board, 'TL-01', 1);
        $empty = $this->toolSlot($this->board, 2);

        $response = $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => '  Impact Wrench ', 'image' => $this->png()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('tool.code', 'TL-02')
            ->assertJsonPath('tool.name', 'Impact Wrench')
            ->assertJsonPath('tool.slot', null)
            ->assertJsonPath('tool.in', false)
            ->assertJsonPath('tool.history', false);

        // The code is the first unused TL-NN; the tool is not put on the empty slot (Edit Slot places it).
        $tool = LototoTool::query()->where('board_id', $this->board->id)->where('code', 'TL-02')->firstOrFail();
        $this->assertSame(['Impact Wrench', null, true], [$tool->name, $tool->location, $tool->is_active]);
        $this->assertStringStartsWith('lototo-tools/'.$this->board->id.'/', $tool->image_path);
        Storage::disk('local')->assertExists($tool->image_path);
        $this->assertStringStartsWith(route('safety.lototo.tools.image', $tool, absolute: false).'?v=', $response->json('tool.image'));
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $empty->id, 'tool_id' => null, 'tool_label' => null]);
        $this->assertSame(0, LototoBoardSlot::query()->where('tool_id', $tool->id)->count());

        $this->actingAs($this->admin)->get(route('safety.lototo.tools.image', $tool))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->admin)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))
            ->assertOk()
            ->assertSee('data-qrcode="'.e(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id])).'"', false);

        // The Edit Mode layout offers it in Edit Slot and in the Kelola Tools list.
        $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))->assertOk()
            ->assertJsonFragment(['id' => $tool->id, 'code' => 'TL-02', 'name' => 'Impact Wrench'])
            ->assertJsonPath('manage.tools.1.code', 'TL-02')
            ->assertJsonPath('manage.tools.0.slot', 'Other / Tools 01');
    }

    public function test_full_flow_from_creating_a_tool_to_reading_its_history(): void
    {
        $slot = $this->toolSlot($this->board, 5);
        $this->toolSlot($this->otherBoard, 5);
        $worker = $this->staffOf($this->unit);

        // 1. Admin creates the tool with an image (Kelola Tools), then places it on position 05 (Edit Slot).
        $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'Torque Wrench', 'image' => $this->png()], ['Accept' => 'application/json'])->assertCreated();
        $tool = LototoTool::query()->where('name', 'Torque Wrench')->firstOrFail();
        $slot->update(['tool_id' => $tool->id, 'tool_label' => $tool->name]);
        $formUrl = route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id]);

        // 2. The QR Code page has its QR; the board shows it on position 05 with its image and link, as OUT.
        $this->actingAs($this->admin)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))
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
        $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))->assertJsonPath('manage.tools.0.in', true);

        // 4. The worker does OUT; the tool is back on the board.
        $open = $tool->openActivity()->firstOrFail();
        $this->actingAs($worker)->get($formUrl)->assertSee('data-tool-status="in"', false)->assertSee('OUT · Kembalikan ke board');
        $this->actingAs($worker)->post(route('safety.lototo.tools.form.store', $tool), ['board_id' => $this->board->id, 'action' => 'out', 'activity_id' => $open->id])
            ->assertRedirect($formUrl)->assertSessionHasNoErrors();
        $board = $this->actingAs($worker)->get(route('safety.lototo.boards.show', $this->board))->getContent();
        $this->assertStringContainsString('aria-label="Other posisi 5 Torque Wrench"', $board);
        $this->assertStringNotContainsString('lototo-tool-status', $board);

        // 5. History shows who did IN and OUT, where and when; the tool can no longer be deleted.
        $this->actingAs($worker)->get($formUrl)->assertOk()
            ->assertSee('Line 2 · Extruder')->assertSee($worker->name)->assertSee(\App\Models\LototoActivity::localTimeFromUtc($open->fresh()->ended_at)->format('d M Y H:i'));
        $this->assertDatabaseHas('lototo_tool_activities', ['id' => $open->id, 'status' => 'finished', 'started_by' => $worker->id, 'ended_by' => $worker->id, 'board_id' => $this->board->id]);
        $slot->update(['tool_id' => null, 'tool_label' => null]);
        $this->actingAs($this->admin)->deleteJson(route('safety.lototo.boards.tools.destroy', [$this->board, $tool]))->assertUnprocessable()->assertJsonValidationErrors('tool');
        $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))->assertJsonPath('manage.tools.0.history', true);
        $this->assertDatabaseHas('lototo_tools', ['id' => $tool->id]);
    }

    public function test_tool_validation_and_permissions(): void
    {
        LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-01', 'name' => 'Kunci']);
        $store = route('safety.lototo.boards.tools.store', $this->board);
        $json = ['Accept' => 'application/json'];

        // The same name (any case) on the same board is refused; on another board it is fine.
        $this->actingAs($this->admin)->post($store, ['name' => 'kunci '], $json)->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.store', $this->otherBoard), ['name' => 'Kunci'], $json)->assertCreated()->assertJsonPath('tool.code', 'TL-01');
        $this->actingAs($this->admin)->post($store, ['name' => '   '], $json)->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Nama Tools wajib diisi.']);
        $this->actingAs($this->admin)->post($store, ['name' => str_repeat('x', 101)], $json)->assertUnprocessable()->assertJsonValidationErrors('name');
        // Real uploads (not UploadedFile::fake(), which trusts the file name) so the content check is exercised.
        $this->actingAs($this->admin)->post($store, ['name' => 'Obeng', 'image' => $this->upload('tool.png', 'not an image')], $json)->assertJsonValidationErrors('image');
        $this->actingAs($this->admin)->post($store, ['name' => 'Obeng', 'image' => $this->png(2100 * 1024)], $json)->assertJsonValidationErrors('image');
        $this->actingAs($this->admin)->post($store, ['name' => 'Obeng', 'image' => $this->upload('tool.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')], $json)->assertJsonValidationErrors('image');
        $this->actingAs($this->admin)->post($store, ['name' => 'Obeng', 'image' => $this->upload('tool.php.png', '<?php echo 1;')], $json)->assertJsonValidationErrors('image');

        $this->assertSame(['TL-01'], LototoTool::query()->where('board_id', $this->board->id)->pluck('code')->all());
        $this->assertSame([], Storage::disk('local')->allFiles(), 'Rejected uploads must not leave files behind.');
        $this->assertSame(0, LototoBoardSlot::query()->whereIn('board_id', [$this->board->id, $this->otherBoard->id])->count(), 'New tools are never placed on a slot.');

        $staff = $this->staffOf($this->unit);
        $tool = LototoTool::query()->where('board_id', $this->board->id)->firstOrFail();
        $this->actingAs($staff)->post($store, ['name' => 'Obeng'], $json)->assertForbidden();
        $this->actingAs($staff)->post(route('safety.lototo.boards.tools.update', [$this->board, $tool]), ['name' => 'X'], $json)->assertForbidden();
        $this->actingAs($staff)->deleteJson(route('safety.lototo.boards.tools.destroy', [$this->board, $tool]))->assertForbidden();
        $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.update', [$this->otherBoard, $tool]), ['name' => 'X'], $json)->assertNotFound();
        $this->actingAs($this->admin)->deleteJson(route('safety.lototo.boards.tools.destroy', [$this->otherBoard, $tool]))->assertNotFound();
        $this->assertDatabaseHas('lototo_tools', ['id' => $tool->id, 'code' => 'TL-01', 'name' => 'Kunci']);
    }

    public function test_editing_a_tool_changes_its_name_and_image_and_keeps_the_old_image_when_none_is_given(): void
    {
        $slot = $this->toolSlot($this->board, 1);
        $json = ['Accept' => 'application/json'];
        $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'Kunci', 'image' => $this->png()], $json)->assertCreated();
        $tool = LototoTool::query()->firstOrFail();
        $slot->update(['tool_id' => $tool->id, 'tool_label' => $tool->name]);
        $firstImage = $tool->image_path;
        $update = route('safety.lototo.boards.tools.update', [$this->board, $tool]);

        // Name only: the image stays; the slot holding the tool shows the new name; the code does not change.
        $this->actingAs($this->admin)->post($update, ['name' => 'Kunci Pas'], $json)->assertOk()
            ->assertJsonPath('tool.name', 'Kunci Pas')->assertJsonPath('tool.code', 'TL-01')->assertJsonPath('tool.slot', 'Other / Tools 01');
        $tool->refresh();
        $this->assertSame(['TL-01', 'Kunci Pas', $firstImage], [$tool->code, $tool->name, $tool->image_path]);
        Storage::disk('local')->assertExists($firstImage);
        $this->assertDatabaseHas('lototo_board_slots', ['id' => $slot->id, 'tool_id' => $tool->id, 'tool_label' => 'Kunci Pas', 'position' => 1]);

        // A new image replaces the old file.
        $this->actingAs($this->admin)->post($update, ['name' => 'Kunci Pas', 'image' => $this->png()], $json)->assertOk();
        $tool->refresh();
        $this->assertNotSame($firstImage, $tool->image_path);
        Storage::disk('local')->assertMissing($firstImage);
        Storage::disk('local')->assertExists($tool->image_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());

        // A failed edit (name of another tool, bad image) keeps the old data and leaves no new file.
        LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-02', 'name' => 'Obeng']);
        $this->actingAs($this->admin)->post($update, ['name' => 'OBENG', 'image' => $this->png()], $json)->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs($this->admin)->post($update, ['name' => 'Kunci Baru', 'image' => $this->upload('tool.png', 'not an image')], $json)->assertJsonValidationErrors('image');
        $this->assertSame(['Kunci Pas', $tool->image_path], [$tool->fresh()->name, $tool->fresh()->image_path]);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_unused_tool_can_be_deleted_but_used_in_or_placed_tools_cannot(): void
    {
        $json = ['Accept' => 'application/json'];
        $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'Unused', 'image' => $this->png()], $json)->assertCreated();
        $unused = LototoTool::query()->where('name', 'Unused')->firstOrFail();
        $unusedImage = $unused->image_path;
        $placed = $this->tool($this->board, 'TL-02', 2);
        $used = LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-03', 'name' => 'Used']);
        $history = LototoToolActivity::query()->create(['tool_id' => $used->id, 'board_id' => $this->board->id, 'location' => 'Line A', 'status' => 'finished', 'started_by' => $this->admin->id, 'ended_by' => $this->admin->id, 'started_at' => now()->subHour(), 'ended_at' => now()]);
        $inUse = LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-04', 'name' => 'In use']);
        $open = LototoToolActivity::query()->create(['tool_id' => $inUse->id, 'board_id' => $this->board->id, 'location' => 'Line B', 'status' => 'in_progress', 'started_by' => $this->admin->id, 'started_at' => now()]);

        $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))->assertOk()
            ->assertJsonPath('manage.tools.1.slot', 'Other / Tools 02')
            ->assertJsonPath('manage.tools.2.history', true)
            ->assertJsonPath('manage.tools.3.in', true);

        $this->actingAs($this->admin)->deleteJson(route('safety.lototo.boards.tools.destroy', [$this->board, $unused]))->assertOk();
        $this->assertDatabaseMissing('lototo_tools', ['id' => $unused->id]);
        Storage::disk('local')->assertMissing($unusedImage);

        foreach ([[$placed, 'masih terpasang di slot Other / Tools 02'], [$used, 'memiliki riwayat'], [$inUse, 'sedang IN']] as [$tool, $reason]) {
            $response = $this->actingAs($this->admin)->deleteJson(route('safety.lototo.boards.tools.destroy', [$this->board, $tool]))->assertUnprocessable();
            $this->assertStringContainsString($reason, $response->json('errors.tool.0'));
            $this->assertDatabaseHas('lototo_tools', ['id' => $tool->id]);
        }
        $this->assertDatabaseHas('lototo_board_slots', ['board_id' => $this->board->id, 'position' => 2, 'tool_id' => $placed->id]);
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

    public function test_the_same_tool_code_on_two_boards_always_resolves_to_the_tool_of_the_given_board(): void
    {
        $mine = $this->tool($this->board, 'TL-01', 1);
        $theirs = $this->tool($this->otherBoard, 'TL-01', 1);

        // Each board's QR and board link carry that board's own tool.
        $this->actingAs($this->admin)->get(route('safety.lototo.qr-codes', ['board' => $this->otherBoard->id]))->assertOk()
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
        $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.update', [$this->board, $theirs]), ['name' => 'X'], ['Accept' => 'application/json'])->assertNotFound();
        $this->assertSame(['Tool 1', 'Tool 1'], [$mine->fresh()->name, $theirs->fresh()->name]);
    }

    public function test_tool_codes_continue_after_the_highest_free_number_and_reuse_gaps(): void
    {
        LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-01', 'name' => 'A']);
        LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-03', 'name' => 'C']);
        $json = ['Accept' => 'application/json'];

        $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'B'], $json)->assertJsonPath('tool.code', 'TL-02');
        $this->actingAs($this->admin)->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'D'], $json)->assertJsonPath('tool.code', 'TL-04');
        $this->assertSame(['TL-01', 'TL-02', 'TL-03', 'TL-04'], LototoTool::query()->where('board_id', $this->board->id)->orderBy('code')->pluck('code')->all());
    }

    public function test_activity_times_are_stored_in_utc_and_shown_in_local_time(): void
    {
        $tool = $this->tool($this->board, 'TL-01', 1);
        $activity = LototoToolActivity::query()->create(['tool_id' => $tool->id, 'board_id' => $this->board->id, 'location' => 'Line A', 'status' => 'finished', 'started_by' => $this->admin->id, 'ended_by' => $this->admin->id, 'started_at' => '2026-10-29 03:05:00', 'ended_at' => '2026-10-29 17:30:00']);

        $this->actingAs($this->admin)->get(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id]))
            ->assertOk()->assertSee('29 Oct 2026 10:05')->assertSee('30 Oct 2026 00:30')->assertDontSee('29 Oct 2026 03:05');
        $this->assertSame('2026-10-29 03:05:00', $activity->fresh()->getRawOriginal('started_at'));
    }

    public function test_tool_activity_timestamps_written_by_the_form_are_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-10-29 03:05:00', 'UTC'));
        $tool = $this->tool($this->board, 'TL-01', 1);

        $this->actingAs($this->admin)
            ->post(route('safety.lototo.tools.form.store', $tool), [
                'board_id' => $this->board->id,
                'action' => 'in',
                'location' => 'Line A',
            ])
            ->assertRedirect(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id]));

        $activity = $tool->activities()->sole();
        $this->assertSame('2026-10-29 03:05:00', $activity->getRawOriginal('started_at'));

        $this->travel(30)->minutes();
        $this->actingAs($this->admin)
            ->post(route('safety.lototo.tools.form.store', $tool), [
                'board_id' => $this->board->id,
                'action' => 'out',
                'activity_id' => $activity->id,
            ])
            ->assertRedirect(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id]));

        $this->assertSame('2026-10-29 03:35:00', $activity->fresh()->getRawOriginal('ended_at'));
    }

    public function test_board_buttons_are_manage_board_and_qr_code(): void
    {
        $html = $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('safety.lototo.boards.index', ['board' => $this->board->id]), '#').'" class="lototo-board-action">\s*<svg[^>]*>.*?</svg>\s*Manage Board\s*</a>#s', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('safety.lototo.qr-codes', ['board' => $this->board->id]), '#').'" class="lototo-board-action">\s*<svg[^>]*>.*?</svg>\s*QR Code\s*</a>#s', $html);
        $this->assertDoesNotMatchRegularExpression('#class="lototo-board-action">\s*<svg[^>]*>.*?</svg>\s*Settings\s*</a>#s', $html);
        // Edit Board is the inline Edit Mode button, not a link to another page; it has the Kelola popups.
        $this->assertMatchesRegularExpression('#<button type="button" class="lototo-board-action" data-board-edit-start>\s*<svg[^>]*>.*?</svg>\s*Edit Board\s*</button>#s', $html);
        $this->assertStringContainsString('data-editor-manage="tools"', $html);
        $this->assertStringContainsString('data-editor-manage="spare"', $html);
        $this->assertStringContainsString('data-tools-url="'.route('safety.lototo.boards.tools.store', $this->board).'"', $html);
        $this->assertStringContainsString('data-spare-locks-url="'.route('safety.lototo.boards.spare-locks.store', $this->board).'"', $html);

        $this->actingAs($this->admin)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))->assertOk()->assertSee('QR Code · Maintenance');
        $staffHtml = $this->actingAs($this->staffOf($this->unit))->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();
        $this->assertStringNotContainsString('lototo-board-action"', $staffHtml);
        $this->actingAs($this->staffOf($this->unit))->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))->assertForbidden();
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
        $this->actingAs($this->admin)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))
            ->assertOk()
            ->assertSee('data-qrcode="'.e(\App\Http\Middleware\AllowLototoQrForm::url('safety.lototo.form', ['spare_lock' => 'SPH-01', 'board' => $this->board->id])).'"', false);
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
