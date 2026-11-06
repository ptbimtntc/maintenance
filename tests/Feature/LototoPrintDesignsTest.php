<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Http\Middleware\AllowLototoQrForm;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\LototoBoard;
use App\Models\LototoTool;
use App\Models\SpareLock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Download QR" on LOTOTO Settings opens the print-design popup: every QR record of the board is offered with its own
 * identity and its existing QR URL, and employee photos come from a same-origin route for Safety managers only.
 */
class LototoPrintDesignsTest extends TestCase
{
    use RefreshDatabase;

    private LototoBoard $board;
    private User $admin;
    private Employee $budi;
    private Employee $andi;
    private Employee $supervisor;
    private SpareLock $spare;
    private LototoTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
        $unit = BusinessUnit::factory()->create();
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $unit->id, 'division_name' => 'Print', 'code' => 'board-print', 'title' => 'BOARD', 'subtitle' => 'Print',
            'layout' => ['board_columns' => 20], 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        // A real 1 x 1 PNG (the test environment has no GD for generated images).
        $photo = 'employee-photos/budi.png';
        Storage::disk('public')->put($photo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->budi = Employee::factory()->create(['business_unit_id' => $unit->id, 'lototo_number' => '07', 'full_name' => 'Budi Santoso', 'preferred_name' => 'Budi', 'photo_path' => $photo]);
        $this->andi = Employee::factory()->create(['business_unit_id' => $unit->id, 'lototo_number' => '12', 'full_name' => 'Andi Wijaya', 'preferred_name' => null, 'photo_path' => null]);
        $this->supervisor = Employee::factory()->create(['business_unit_id' => $unit->id, 'lototo_number' => '101', 'full_name' => 'Sari Dewi', 'preferred_name' => null]);
        $this->spare = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-01', 'is_active' => true]);
        $this->tool = LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-01', 'name' => 'Gerinda', 'is_active' => true]);
    }

    public function test_download_qr_opens_the_popup_with_each_record_and_its_own_qr_url(): void
    {
        $page = $this->actingAs($this->admin)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))->assertOk()->getContent();

        // The buttons open the popup instead of downloading; the old direct download is gone from this page.
        $this->assertStringNotContainsString('data-download-qr', $page);
        foreach (["technician-{$this->budi->id}", "technician-{$this->andi->id}", "supervisor-{$this->supervisor->id}", "spare-{$this->spare->id}", "tool-{$this->tool->id}"] as $key) {
            $this->assertStringContainsString('data-print-open="'.$key.'"', $page);
        }
        $this->assertStringContainsString('<dialog class="lototo-print-dialog" data-print-designs', $page);
        foreach (['data-print-select-all>Pilih Semua', 'data-print-select-none>Batalkan Pilihan', 'data-print-close>Tutup', 'data-print-download disabled>Download'] as $control) {
            $this->assertStringContainsString($control, $page);
        }

        preg_match('/<script type="application\/json" data-print-records>(.*?)<\/script>/s', $page, $json);
        $records = collect(json_decode($json[1], true))->keyBy('key');
        $qr = fn (array $parameters) => AllowLototoQrForm::url('safety.lototo.form', [...$parameters, 'board' => $this->board->id]);

        $this->assertSame([
            'key' => "technician-{$this->budi->id}", 'kind' => 'technician', 'group' => 'Technician Locks', 'name' => 'BUDI SANTOSO', 'number' => '07',
            'photo' => route('safety.lototo.print.photo', $this->budi, absolute: false).'?v='.$this->budi->updated_at->timestamp,
            'qr' => $qr(['lototo_number' => '07', 'employee_id' => $this->budi->id]),
            'activity' => url(route('safety.lototo.show', ['employee' => $this->budi->id, 'board' => $this->board->id], absolute: false)),
        ], $records["technician-{$this->budi->id}"]);
        // Spare locks and tools have no activity page: Desain 4 keeps their own QR.
        $this->assertArrayNotHasKey('activity', $records["spare-{$this->spare->id}"]);
        $this->assertArrayNotHasKey('activity', $records["tool-{$this->tool->id}"]);
        // No photo: nothing is invented.
        $this->assertSame(['ANDI WIJAYA', '12', null], [$records["technician-{$this->andi->id}"]['name'], $records["technician-{$this->andi->id}"]['number'], $records["technician-{$this->andi->id}"]['photo']]);
        $this->assertSame(['supervisor', 'SARI DEWI', '101'], [$records["supervisor-{$this->supervisor->id}"]['kind'], $records["supervisor-{$this->supervisor->id}"]['name'], $records["supervisor-{$this->supervisor->id}"]['number']]);
        // Spare Lock: its own code and QR, no employee identity or photo.
        $this->assertSame(['spare', 'SPARE', 'SP-01', null, $qr(['spare_lock' => 'SP-01'])], array_values(array_intersect_key($records["spare-{$this->spare->id}"], array_flip(['kind', 'name', 'number', 'photo', 'qr']))));
        // Tools: the tool's own name, code and QR (the Tools form link).
        $this->assertSame(['tool', 'GERINDA', 'TL-01', null, route('safety.lototo.tools.form', ['tool' => $this->tool->id, 'board' => $this->board->id])], array_values(array_intersect_key($records["tool-{$this->tool->id}"], array_flip(['kind', 'name', 'number', 'photo', 'qr']))));

        // Every record's QR URL is exactly the one its QR card on the page already shows (no new QR is made).
        foreach ($records as $record) {
            $this->assertStringContainsString('data-qrcode="'.e($record['qr']).'"', $page, $record['key']);
        }
        // Desain 4's URL is the existing LOTOTO Activity page of that employee on this board.
        $this->actingAs($this->admin)->get($records["technician-{$this->budi->id}"]['activity'])->assertOk()->assertSee('Budi');
        auth()->logout();
        $this->get($records["technician-{$this->budi->id}"]['activity'])->assertRedirect(route('guest-login'));
        // The identity list holds every record.
        $this->assertSame(5, substr_count(preg_replace('/.*<select data-print-identity[^>]*>(.*?)<\/select>.*/s', '$1', $page), '<option '));
    }

    public function test_a_replaced_photo_gets_a_new_url_so_the_browser_fetches_it_again(): void
    {
        $records = fn () => collect(json_decode(preg_replace('/.*<script type="application\/json" data-print-records>(.*?)<\/script>.*/s', '$1', $this->actingAs($this->admin)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))->getContent()), true))->keyBy('key');
        $before = $records()["technician-{$this->budi->id}"]['photo'];

        $this->travel(5)->minutes();
        Storage::disk('public')->put('employee-photos/budi-new.png', Storage::disk('public')->get('employee-photos/budi.png'));
        $this->budi->update(['photo_path' => 'employee-photos/budi-new.png']);
        $after = $records()["technician-{$this->budi->id}"]['photo'];

        $this->assertNotSame($before, $after);
        $this->assertStringStartsWith(route('safety.lototo.print.photo', $this->budi, absolute: false).'?v=', $after);
        // The versioned URL still serves this employee's current file.
        $this->actingAs($this->admin)->get($after)->assertOk()->assertHeader('Content-Type', 'image/png');
        // Andi has none: still null (the browser draws the silhouette), never Budi's photo.
        $this->assertNull($records()["technician-{$this->andi->id}"]['photo']);
    }

    public function test_the_guest_viewer_sees_and_opens_the_qr_code_page_without_manage_safety(): void
    {
        $guest = User::factory()->create();
        $guest->assignRole(RoleName::Guest->value);
        $this->assertFalse($guest->can('safety.manage'));
        // The board is offered while its Business Unit has an active employee (the board's own rule).
        $this->budi->update(['employment_status_id' => \App\Models\EmploymentStatus::factory()->create(['code' => 'ACTIVE'])->id]);

        $board = $this->actingAs($guest)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('safety.lototo.qr-codes', ['board' => $this->board->id]).'" class="lototo-board-action"', $board);
        $this->assertStringNotContainsString(route('safety.lototo.boards.index', ['board' => $this->board->id]), $board);
        $this->assertStringNotContainsString('data-board-edit-start', $board);

        $page = $this->actingAs($guest)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))->assertOk()
            ->assertSee('data-print-open="technician-'.$this->budi->id.'"', false)
            ->assertDontSee('Manage Board')
            ->getContent();
        $this->assertStringContainsString('<dialog class="lototo-print-dialog" data-print-designs', $page);
        $this->actingAs($guest)->get(route('safety.lototo.print.photo', $this->budi))->assertOk();

        // Everything that changes data or manages boards stays closed to the guest.
        $this->actingAs($guest)->get(route('safety.lototo.boards.index'))->assertForbidden();
        $this->actingAs($guest)->getJson(route('safety.lototo.boards.layout', $this->board))->assertForbidden();
        $this->actingAs($guest)->post(route('safety.lototo.boards.store'), ['division_name' => 'X', 'business_unit_id' => $this->board->business_unit_id, 'spare_lock_count' => 1])->assertForbidden();
        $this->actingAs($guest)->patch(route('safety.lototo.boards.status', $this->board), ['is_active' => 0])->assertForbidden();
        $this->actingAs($guest)->postJson(route('safety.lototo.boards.spare-locks.store', $this->board))->assertForbidden();
        $this->actingAs($guest)->postJson(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'X'])->assertForbidden();
        $this->assertTrue($this->board->fresh()->is_active);

        // Other Safety viewers (no Manage Safety, not Guest) still get no QR page.
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->actingAs($staff)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))->assertForbidden();
    }

    public function test_employee_photos_are_served_from_this_application_to_safety_managers_only(): void
    {
        $this->actingAs($this->admin)->get(route('safety.lototo.print.photo', $this->budi))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->actingAs($this->admin)->get(route('safety.lototo.print.photo', $this->andi))->assertNotFound();

        $staff = User::factory()->create();
        $this->actingAs($staff)->get(route('safety.lototo.print.photo', $this->budi))->assertForbidden();
        $this->actingAs($staff)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))->assertForbidden();
        auth()->logout();
        $this->get(route('safety.lototo.print.photo', $this->budi))->assertRedirect();
    }
}
