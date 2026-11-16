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
use App\Support\LototoEmployeePhoto;
use App\Support\LototoSlotGrid;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What a board card shows: the place of work of active LOTOTO and Tool activities, the employee's photo (through the
 * application's own route, initials when there is no file), hidden slot numbers that stay in the data, and the IN/OUT
 * form's fixed identity fields.
 */
class LototoBoardCardsTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $unit;
    private LototoBoard $board;
    private User $admin;
    private Employee $budi;
    private Employee $sari;
    private Employee $spv;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        LototoEmployeePhoto::forget();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->unit = BusinessUnit::factory()->create(['name' => 'Tire Cord', 'is_active' => true]);
        $active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $this->unit->id, 'division_name' => 'Maintenance', 'code' => 'mtn_tr', 'title' => 'MAINTENANCE · TIRE CORD', 'subtitle' => 'x',
            'layout' => ['board_columns' => 20], 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $make = fn (string $number, string $name) => Employee::factory()->create(['business_unit_id' => $this->unit->id, 'employment_status_id' => $active->id, 'lototo_number' => $number, 'full_name' => $name]);
        $this->budi = $make('07', 'Budi Teknisi');
        $this->sari = $make('08', 'Sari Teknisi');
        $this->spv = $make('101', 'Dewi Supervisor');
        foreach ([[$this->budi, 'technician', 7], [$this->sari, 'technician', 8], [$this->spv, 'supervisor', 1]] as [$employee, $category, $position]) {
            LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => $category, 'position' => $position, 'is_enabled' => true, 'employee_id' => $employee->id]);
        }
        LototoSlotGrid::sync($this->board);
    }

    public function test_active_technician_supervisor_and_spare_show_their_current_place_of_work(): void
    {
        $this->activity($this->budi, 'Extruder Line 2 - Gearbox', 'in_progress');
        // Sari finished an older job; she is OUT now, so that place must not show.
        $this->activity($this->sari, 'Kalender 3', 'completed');
        $this->activity($this->spv, 'Ruang MCC', 'in_progress');
        $spare = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-TC-01', 'is_active' => true]);
        LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'spare', 'position' => 1, 'is_enabled' => true, 'spare_lock_id' => $spare->id]);
        LototoSlotGrid::sync($this->board);
        $spareActivity = $this->activity($this->sari, 'Packing Line 1', 'in_progress', $spare);
        $spare->update(['current_activity_id' => $spareActivity->id]);

        $board = $this->boardHtml();
        $card = fn (Employee $e) => $this->card($board, $e);

        // Each open employee activity supplies its own place, under the IN tag.
        $this->assertMatchesRegularExpression('/lototo-lock-danger--in"><span>IN<\/span><\/span>\s*<span class="lototo-lock-location" title="Extruder Line 2 - Gearbox">.*?class="lototo-lock-location__text">Extruder Line 2 - Gearbox<\/span>/s', $card($this->budi));
        $this->assertStringContainsString('class="lototo-location-icon"', $card($this->budi));
        $this->assertMatchesRegularExpression('/<svg class="lototo-location-icon"[^>]*>.*?fill="#dc2626" stroke="#facc15".*?fill="#facc15".*?<\/svg>/s', $card($this->budi));
        $this->assertStringContainsString('Budi', $card($this->budi));
        $this->assertStringContainsString('lototo-number">07<', $card($this->budi));
        // Nothing from an older, finished activity; Supervisor uses the same active-activity source as Technician.
        $this->assertStringNotContainsString('lototo-lock-location', $card($this->sari));
        $this->assertStringNotContainsString('Kalender 3', $board);
        $this->assertStringContainsString('status IN', $card($this->spv));
        $this->assertStringContainsString('title="Ruang MCC"', $card($this->spv));
        $this->assertStringContainsString('class="lototo-lock-location__text">Ruang MCC</span>', $card($this->spv));
        $this->assertStringContainsString('class="lototo-location-icon"', $card($this->spv));
        $this->assertMatchesRegularExpression('/<span class="lototo-lock-location" title="Packing Line 1">\s*<svg class="lototo-location-icon".*?<\/svg>\s*<span class="lototo-lock-location__text">Packing Line 1<\/span>/s', $board);

        // Two technicians IN at once: each card has its own place.
        $this->activity($this->sari, 'Gudang Bahan', 'in_progress');
        $board = $this->boardHtml();
        $this->assertStringContainsString('>Gudang Bahan<', $this->card($board, $this->sari));
        $this->assertStringNotContainsString('Gudang Bahan', $this->card($board, $this->budi));
        $this->assertStringNotContainsString('Extruder', $this->card($board, $this->sari));

        // An empty place shows no text at all (and never another activity's).
        LototoActivity::query()->where('employee_id', $this->budi->id)->where('status', 'in_progress')->update(['location' => '  ']);
        $this->assertStringNotContainsString('lototo-lock-location', $this->card($this->boardHtml(), $this->budi));
        // Back OUT: no place either, even if a stale spare current_activity_id still points at a completed activity.
        LototoActivity::query()->where('employee_id', $this->sari->id)->update(['status' => 'completed']);
        $this->assertStringNotContainsString('lototo-lock-location', $this->card($this->boardHtml(), $this->sari));
        $spareActivity->update(['status' => 'completed']);
        $spareHtml = $this->boardHtml();
        $this->assertStringNotContainsString('title="Packing Line 1"', $spareHtml);
        $this->assertStringNotContainsString('lototo-active-card--spare', $spareHtml);
    }

    public function test_tools_show_their_image_when_out_and_the_active_location_instead_when_in(): void
    {
        $tool = LototoTool::query()->create([
            'board_id' => $this->board->id,
            'code' => 'TL-01',
            'name' => 'Torque Wrench',
            'image_path' => 'lototo-tools/'.$this->board->id.'/torque.png',
            'is_active' => true,
        ]);
        Storage::disk('local')->put($tool->image_path, 'tool image');
        $slot = LototoBoardSlot::query()->create([
            'board_id' => $this->board->id,
            'category' => 'tool',
            'position' => 1,
            'is_enabled' => true,
            'tool_id' => $tool->id,
        ]);

        $outCard = $this->toolCard($this->boardHtml(), 1);
        $this->assertStringContainsString('data-slot-id="'.$slot->id.'"', $outCard);
        $this->assertStringContainsString('href="'.e(route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $this->board->id])).'"', $outCard);
        $this->assertStringContainsString('class="lototo-tool-image"', $outCard);
        $this->assertStringNotContainsString('lototo-tool-status', $outCard);

        $activity = LototoToolActivity::query()->create([
            'tool_id' => $tool->id,
            'board_id' => $this->board->id,
            'location' => 'Utilities Room',
            'status' => 'in_progress',
            'started_by' => $this->admin->id,
            'started_at' => now('UTC'),
        ]);
        $inCard = $this->toolCard($this->boardHtml(), 1);
        $this->assertStringContainsString('<span class="lototo-tool-status">IN</span>', $inCard);
        $this->assertStringContainsString('title="Utilities Room"', $inCard);
        $this->assertStringContainsString('class="lototo-lock-location__text">Utilities Room</span>', $inCard);
        $this->assertStringContainsString('class="lototo-location-icon"', $inCard);
        $this->assertMatchesRegularExpression('/<svg class="lototo-location-icon"[^>]*>.*?fill="#dc2626" stroke="#facc15".*?fill="#facc15".*?<\/svg>/s', $inCard);
        $this->assertStringContainsString('Torque Wrench', $inCard);
        $this->assertStringNotContainsString('lototo-tool-image', $inCard);

        $activity->update(['status' => 'finished', 'ended_at' => now('UTC')]);
        $outAgain = $this->toolCard($this->boardHtml(), 1);
        $this->assertStringContainsString('class="lototo-tool-image"', $outAgain);
        $this->assertStringNotContainsString('lototo-tool-status', $outAgain);
        $this->assertStringNotContainsString('Utilities Room', $outAgain);
    }

    public function test_photos_come_from_the_applications_own_route_and_belong_to_their_employee(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('employee-photos/budi.png', $png.'BUDI');
        Storage::disk('public')->put('employee-photos/sari.png', $png.'SARI');
        $this->budi->update(['photo_path' => 'employee-photos/budi.png']);
        $this->sari->update(['photo_path' => 'employee-photos/sari.png']);
        // Dewi has a photo recorded, but the file is not there: initials, never a broken image.
        $this->spv->update(['photo_path' => 'employee-photos/missing.png']);
        LototoEmployeePhoto::forget();

        $board = $this->boardHtml();
        foreach ([[$this->budi, 'BUDI'], [$this->sari, 'SARI']] as [$employee, $tag]) {
            $card = $this->card($board, $employee);
            // Relative URL of this application (no host, no /storage link, no APP_URL), carrying this employee's id.
            $this->assertMatchesRegularExpression('#<img\s+src="/safety/lototo/employees/'.$employee->id.'/photo\?v=\d+"\s+alt="'.preg_quote($employee->full_name, '#').'"#', $card);
            $this->assertStringNotContainsString('/storage/', $card);
            $response = $this->actingAs($this->admin)->get(route('safety.lototo.employee-photo', $employee))->assertOk();
            $this->assertSame($png.$tag, $response->streamedContent(), 'the photo of the employee it is asked for');
        }
        $dewi = $this->card($board, $this->spv);
        $this->assertStringNotContainsString('<img', $dewi);
        $this->assertStringContainsString('lototo-photo--initials">DS<', $dewi);
        $this->actingAs($this->admin)->get(route('safety.lototo.employee-photo', $this->spv))->assertNotFound();
        $this->actingAs($this->admin)->get(route('safety.lototo.employee-photo', $this->spv))->assertNotFound();

        // Anyone who may see the board sees the photos (Guest Viewer, staff); a visitor without a login does not.
        foreach ([RoleName::Guest, RoleName::MaintenanceStaff] as $role) {
            $viewer = User::factory()->create();
            $viewer->assignRole($role->value);
            if ($role === RoleName::MaintenanceStaff) {
                $this->sari->update(['user_id' => $viewer->id]); // a signed-in employee of this Business Unit
            }
            $this->actingAs($viewer)->get(route('safety.lototo.employee-photo', $this->budi))->assertOk();
            $this->actingAs($viewer)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->assertSee('/safety/lototo/employees/'.$this->budi->id.'/photo', false);
        }
        auth()->logout();
        $this->get(route('safety.lototo.employee-photo', $this->budi))->assertRedirect();
        // Still there after a refresh, on every request.
        $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->assertSee('/safety/lototo/employees/'.$this->sari->id.'/photo', false);
    }

    public function test_photo_paths_saved_in_other_formats_are_found(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('employee-photos/format.png', $png);
        foreach (['employee-photos/format.png', '/employee-photos/format.png', 'public/employee-photos/format.png', 'storage/employee-photos/format.png', 'format.png', 'employee-photos\\format.png'] as $stored) {
            $this->budi->update(['photo_path' => $stored]);
            LototoEmployeePhoto::forget();
            $this->assertSame('employee-photos/format.png', LototoEmployeePhoto::path($this->budi->fresh()), $stored);
            $this->actingAs($this->admin)->get(route('safety.lototo.employee-photo', $this->budi))->assertOk();
        }
        // Never a path outside the photo folder.
        foreach (['../.env', 'employee-photos/../../.env', ''] as $stored) {
            $this->budi->update(['photo_path' => $stored]);
            LototoEmployeePhoto::forget();
            $this->assertNull(LototoEmployeePhoto::path($this->budi->fresh()), "path [{$stored}]");
        }
    }

    public function test_slot_numbers_are_hidden_with_css_but_stay_in_the_markup_and_the_data(): void
    {
        $board = $this->boardHtml();
        // Still rendered: the element, the slot's position and id are what Edit Board and the tests read.
        $this->assertStringContainsString('class="lototo-position-index">07<', $board);
        $this->assertMatchesRegularExpression('/data-slot-category="technician" data-slot-position="7"[^>]*data-slot-id="'.LototoBoardSlot::query()->where('position', 7)->where('board_id', $this->board->id)->value('id').'"/', $board);
        // ...and hidden for the board and for Edit Board (the editor draws its cards inside .lototo-board-content).
        $css = file_get_contents(resource_path('css/lototo.css'));
        $this->assertMatchesRegularExpression('/\.lototo-page \.lototo-board-content \.lototo-position-index \{\s*display: none !important;\s*\}/', $css);
        // The layout the editor reads and saves is unchanged: positions are still in the data.
        $layout = $this->actingAs($this->admin)->getJson(route('safety.lototo.boards.layout', $this->board))->assertOk()->json();
        $this->assertContains(7, array_column($layout['slots']['technician'], 'position'));
    }

    public function test_technician_out_keeps_its_diagonal_pattern_and_all_in_locks_drop_the_header_stripes(): void
    {
        $css = file_get_contents(resource_path('css/lototo.css'));
        // The standard OUT lock still has red/white stripes; active locks of each category have no striped header.
        $this->assertStringContainsString('background: repeating-linear-gradient(135deg, var(--lototo-lock-stripe) 0 7px, #fff 7px 14px);', $css);
        $this->assertMatchesRegularExpression('/\.lototo-board-content \.lototo-card\.lototo-active-card::before \{\s*content: none;/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.lototo-board-content \.lototo-card\.lototo-lock-card:not\([^)]+\) \{\s*background: #fff;/', $css);
        // The yellow and blue patterns of the other sections are still defined.
        $this->assertStringContainsString('repeating-linear-gradient(135deg, #facc15 0 6px, #fff 6px 12px)', $css);
        $this->assertStringContainsString('repeating-linear-gradient(135deg, #1683ca 0 6px, #fff 6px 12px)', $css);
        // The status indicators keep their markup: DANGER tag and IN band.
        $board = $this->boardHtml();
        $this->assertStringContainsString('<span class="lototo-lock-danger">DANGER</span>', $board);
    }

    public function test_location_display_uses_shared_11px_icon_and_fixed_font_size_for_all_cards(): void
    {
        $css = file_get_contents(resource_path('css/lototo.css'));
        $this->assertMatchesRegularExpression('/\.lototo-card \.lototo-lock-location \{[^}]*width: max-content;[^}]*align-items: center;[^}]*justify-content: center;[^}]*gap: 4px;/s', $css);
        $this->assertMatchesRegularExpression('/\.lototo-card \.lototo-location-icon \{[^}]*width: 11px;[^}]*height: 11px;[^}]*flex: 0 0 11px;/s', $css);
        $this->assertMatchesRegularExpression('/\.lototo-card \.lototo-lock-location \{[^}]*font-size: 8px;/s', $css);
        $this->assertDoesNotMatchRegularExpression('/\.lototo-card \.lototo-lock-location \{[^}]*font-size:[^;]*cqi/s', $css);
    }

    public function test_the_in_out_form_fields_are_fixed_and_the_server_does_not_trust_what_is_posted(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->budi->update(['user_id' => $staff->id]);
        $qr = AllowLototoQrForm::url('safety.lototo.form', ['lototo_number' => '07', 'employee_id' => $this->budi->id, 'board' => $this->board->id]);

        $form = $this->get($qr)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<input[^>]*id="lototo_number"[^>]*value="07"[^>]*\sreadonly\s/s', $form);
        $this->assertMatchesRegularExpression('/<input[^>]*id="lototo_board_display"[^>]*readonly/s', $form);
        $this->assertStringNotContainsString('bg-red-600', $form);
        // readonly (not disabled): the value is still sent with the form.
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="lototo_number"[^>]*\sdisabled/s', $form);

        // Posting another number, or another board's name, changes nothing: the signed QR decides who acts, and the
        // activity gets the board of the request, not the posted text.
        $post = fn (array $extra) => $this->post(preg_replace('#/safety/lototo/form\?.*#', '', route('safety.lototo.form.store')) ? AllowLototoQrForm::url('safety.lototo.form.store', ['lototo_number' => '07', 'employee_id' => $this->budi->id, 'board' => $this->board->id]) : '', [
            'lototo_number' => '07', 'employee_id' => $this->budi->id, 'board_id' => $this->board->id, 'lock_status' => 'in', 'location' => 'Line 5', 'activity' => 'Ganti bearing',
            'risks' => ['kesetrum'], 'risk_controls' => 'Isolasi', 'knows_procedure' => 'ya', 'follows_lototo' => 'ya', 'uses_ppe' => 'ya', 'safe_tools' => 'ya', 'safe_hands' => 'ya', ...$extra,
        ]);
        // Another employee's number with this QR's employee id: no such employee, nothing is saved.
        $post(['lototo_number' => '08'])->assertNotFound();
        $this->assertSame(0, LototoActivity::query()->count());
        $post(['lototo_board' => 'board-palsu'])->assertSessionHasNoErrors();
        $activity = LototoActivity::query()->sole();
        $this->assertSame(['mtn_tr', $this->budi->id], [$activity->board, $activity->employee_id]);
    }

    public function test_the_number_stays_editable_only_where_the_system_cannot_fill_it(): void
    {
        $lock = \App\Models\SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-TC-01', 'is_active' => true]);
        // A Safety manager starting a free spare lock enters the employee's number: no value to fix.
        $form = $this->actingAs($this->admin)->get(route('safety.lototo.form', ['spare_lock' => $lock->code, 'board' => $this->board->id]))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="lototo_number"[^>]*\sreadonly/s', $form);
        $this->assertStringNotContainsString('bg-red-600', $form);
    }

    public function test_every_division_board_shows_its_own_places_photos_and_no_numbers(): void
    {
        // A second division (another Business Unit, another board) next to the first.
        $unit = BusinessUnit::factory()->create(['name' => 'Half Product', 'is_active' => true]);
        $board = LototoBoard::query()->create([
            'business_unit_id' => $unit->id, 'division_name' => 'Maintenance', 'code' => 'mtn_hp', 'title' => 'MAINTENANCE · HALF PRODUCT', 'subtitle' => 'x',
            'layout' => ['board_columns' => 20], 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $rina = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => EmploymentStatus::query()->where('code', 'ACTIVE')->value('id'),
            'lototo_number' => '03', 'full_name' => 'Rina Divisi Dua', 'photo_path' => 'employee-photos/rina.png']);
        Storage::disk('public')->put('employee-photos/rina.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg=='));
        LototoBoardSlot::query()->create(['board_id' => $board->id, 'category' => 'technician', 'position' => 3, 'is_enabled' => true, 'employee_id' => $rina->id]);
        LototoSlotGrid::sync($board);
        LototoActivity::query()->create(['employee_id' => $rina->id, 'board' => 'mtn_hp', 'location' => 'Mixer 9', 'activity' => 'Cek', 'risks' => ['x'], 'risk_controls' => 'c',
            'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true, 'safe_tools' => true, 'safe_hands' => true, 'status' => 'in_progress', 'started_at' => now('UTC')]);
        LototoEmployeePhoto::forget();

        $html = $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $board))->assertOk()->getContent();
        $card = $this->card($html, $rina);
        $this->assertStringContainsString('>Mixer 9<', $card);
        $this->assertStringContainsString('/safety/lototo/employees/'.$rina->id.'/photo', $card);
        // Nothing of the first division leaks in, and the slot number is hidden on this board as well.
        $this->assertStringNotContainsString('Budi Teknisi', $html);
        $this->assertStringContainsString('class="lototo-position-index">03<', $html);
        $this->actingAs($this->admin)->get(route('safety.lototo.employee-photo', $rina))->assertOk();
    }

    /** One technician or supervisor's activity (the open one has no end). */
    private function activity(Employee $employee, string $location, string $status, ?SpareLock $spareLock = null): LototoActivity
    {
        return LototoActivity::query()->create(['employee_id' => $employee->id, 'spare_lock_id' => $spareLock?->id, 'board' => 'mtn_tr', 'location' => $location, 'activity' => 'Ganti bearing', 'risks' => ['listrik'],
            'risk_controls' => 'LOTO', 'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true, 'safe_tools' => true, 'safe_hands' => true,
            'status' => $status, 'started_at' => now('UTC'), 'ended_at' => $status === 'completed' ? now('UTC') : null]);
    }

    private function boardHtml(): string
    {
        return $this->actingAs($this->admin)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();
    }

    /** The <a ...>...</a> of one employee's card on the board. */
    private function card(string $html, Employee $employee): string
    {
        preg_match('/<a[^>]*aria-label="'.preg_quote($employee->full_name, '/').' LOTOTO [^"]*".*?<\/a>/s', $html, $m);
        $this->assertNotEmpty($m, "card of {$employee->full_name}");

        return $m[0];
    }

    private function toolCard(string $html, int $position): string
    {
        preg_match('/<a[^>]*data-slot-category="tool" data-slot-position="'.$position.'".*?<\/a>/s', $html, $m);
        $this->assertNotEmpty($m, "tool slot {$position}");

        return $m[0];
    }
}
