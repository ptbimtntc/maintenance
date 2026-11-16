<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
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

/**
 * A signed-in user without Manage Safety reads the LOTOTO Activity of colleagues, as the Guest Viewer does, and still
 * cannot change or manage anything.
 */
class LototoActivityAccessTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $unit;
    private EmploymentStatus $active;
    private LototoBoard $board;
    private User $staff;
    private Employee $staffEmployee;
    private Employee $colleague;
    private LototoActivity $colleagueActivity;
    private SpareLock $spare;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->unit = BusinessUnit::factory()->create(['name' => 'Tire Cord', 'is_active' => true]);
        $this->active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $this->unit->id, 'division_name' => 'Maintenance', 'code' => 'mtn_tr', 'title' => 'MAINTENANCE · TIRE CORD', 'subtitle' => 'x',
            'layout' => ['board_columns' => 20], 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        // A regular user: Maintenance Staff, their own employee record, no Manage Safety.
        $this->staff = User::factory()->create();
        $this->staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->staffEmployee = $this->employee('07', 'Andi Staf', $this->staff->id);
        $this->colleague = $this->employee('08', 'Budi Rekan');
        foreach ([$this->staffEmployee, $this->colleague] as $position => $employee) {
            LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'technician', 'position' => $position + 7, 'is_enabled' => true, 'employee_id' => $employee->id]);
        }
        $this->spare = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-TC-01', 'is_active' => true]);
        $this->colleagueActivity = LototoActivity::query()->create(['employee_id' => $this->colleague->id, 'board' => 'mtn_tr', 'location' => 'Gearbox Line 4', 'activity' => 'Ganti bearing utama',
            'risks' => ['listrik'], 'risk_controls' => 'LOTO', 'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true, 'safe_tools' => false, 'safe_hands' => true,
            'supervisor_feedback' => 'Kunci torsi retak', 'status' => 'completed', 'outcome' => 'finish', 'started_at' => now('UTC')->subHours(3), 'ended_at' => now('UTC')->subHour()]);
    }

    public function test_a_regular_user_does_not_get_manage_safety_and_roles_are_unchanged(): void
    {
        $this->assertFalse($this->staff->can(PermissionName::ManageSafety->value));
        $this->assertTrue($this->staff->can(PermissionName::ViewSafety->value));
        $this->assertFalse($this->staff->can(PermissionName::ViewAllEmployees->value), 'nothing was added to the role');
        // The role definitions are as they were: only Administrator and Maintenance Manager manage Safety.
        foreach (RoleName::cases() as $role) {
            $this->assertSame(
                in_array($role, [RoleName::Administrator, RoleName::MaintenanceManager], true),
                in_array(PermissionName::ManageSafety->value, $role->defaultPermissions(), true),
                $role->value,
            );
        }
    }

    public function test_a_regular_user_reads_the_activity_of_a_colleague_like_the_guest_viewer(): void
    {
        $guest = User::factory()->create();
        $guest->assignRole(RoleName::Guest->value);
        $page = fn (User $user) => $this->actingAs($user)->get(route('safety.lototo.show', ['employee' => $this->colleague, 'board' => $this->board->id]))->assertOk()->getContent();

        $asStaff = $page($this->staff);
        foreach (['Budi Rekan', 'Gearbox Line 4', 'Ganti bearing utama', 'Kunci torsi retak'] as $text) {
            $this->assertStringContainsString($text, $asStaff);
        }
        // The same page the Guest Viewer gets (token and the viewer's own name in the top bar aside).
        $clean = fn (string $html) => preg_replace(['/name="_token" value="[^"]+"/', '/csrf-token" content="[^"]+"/', '/\s+/'], ['', '', ' '], preg_replace('#<(header|nav|aside)[^>]*>.*?</\1>#s', '', substr($html, strpos($html, 'lototo-activity-page') - 60)));
        $this->assertSame($clean($page($guest)), $clean($asStaff));
        // Read only: nothing on the page changes data (no form posts anything).
        $this->assertStringNotContainsString('method="POST"', substr($asStaff, strpos($asStaff, 'lototo-activity-page')));
        // Their own page still works, and the spare lock page is open too.
        $this->actingAs($this->staff)->get(route('safety.lototo.show', ['employee' => $this->staffEmployee, 'board' => $this->board->id]))->assertOk();
        $this->actingAs($this->staff)->get(route('safety.lototo.spare-locks.show', $this->spare))->assertOk()->assertSee('SP-TC-01');
        $this->actingAs($guest)->get(route('safety.lototo.spare-locks.show', $this->spare))->assertOk();

        // Only employees of the board's own Business Unit; a stranger is not found, a visitor without a login is sent away.
        $other = $this->employee('09', 'Orang Lain', null, BusinessUnit::factory()->create()->id);
        $this->actingAs($this->staff)->get(route('safety.lototo.show', ['employee' => $other, 'board' => $this->board->id]))->assertNotFound();
        auth()->logout();
        $this->get(route('safety.lototo.show', ['employee' => $this->colleague, 'board' => $this->board->id]))->assertRedirect();
        // A user without Safety access at all stays out.
        $none = User::factory()->create();
        $this->actingAs($none)->get(route('safety.lototo.show', ['employee' => $this->colleague, 'board' => $this->board->id]))->assertForbidden();
    }

    public function test_reading_gives_no_way_to_change_delete_or_manage(): void
    {
        $this->colleagueActivity->update(['supervisor_follow_up_status' => 'pending']);
        $before = [LototoActivity::query()->get()->toArray(), LototoBoardSlot::query()->count(), SpareLock::query()->count(), LototoBoard::query()->count()];
        $json = ['Accept' => 'application/json'];
        $draft = ['version' => 'x', 'title' => 'HACK', 'subtitle' => 'x', 'board_columns' => 20, 'apply_columns' => false, 'section_order' => ['spare', 'supervisor', 'other'],
            'components' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'slots' => ['technician' => [], 'supervisor' => [], 'spare' => [], 'tool' => []]];

        // Follow-up of someone else's activity: only that employee's supervisor may open or answer it.
        $this->actingAs($this->staff)->get(route('safety.lototo.follow-up', $this->colleagueActivity))->assertForbidden();
        $this->actingAs($this->staff)->post(route('safety.lototo.follow-up.store', $this->colleagueActivity), ['supervisor_follow_up' => 'Atas nama atasan'])->assertForbidden();
        // Board management and Edit Mode: Manage Safety only.
        $this->actingAs($this->staff)->getJson(route('safety.lototo.boards.layout', $this->board))->assertForbidden();
        $this->actingAs($this->staff)->putJson(route('safety.lototo.boards.layout.update', $this->board), $draft)->assertForbidden();
        $this->actingAs($this->staff)->get(route('safety.lototo.boards.index'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('safety.lototo.boards.store'), ['division_name' => 'X', 'business_unit_id' => $this->unit->id, 'spare_lock_count' => 1], $json)->assertForbidden();
        $this->actingAs($this->staff)->patch(route('safety.lototo.boards.status', $this->board), ['is_active' => 0], $json)->assertForbidden();
        $this->actingAs($this->staff)->delete(route('safety.lototo.boards.destroy', $this->board), [], $json)->assertForbidden();
        $this->actingAs($this->staff)->post(route('safety.lototo.boards.spare-locks.store', $this->board), [], $json)->assertForbidden();
        $this->actingAs($this->staff)->delete(route('safety.lototo.boards.spare-locks.destroy', [$this->board, $this->spare]), [], $json)->assertForbidden();
        $this->actingAs($this->staff)->post(route('safety.lototo.boards.tools.store', $this->board), ['name' => 'X'], $json)->assertForbidden();
        // There is no route that edits or deletes an activity; the read page does not accept writes.
        $this->actingAs($this->staff)->put(route('safety.lototo.show', ['employee' => $this->colleague, 'board' => $this->board->id]), ['location' => 'X'])->assertStatus(405);
        $this->actingAs($this->staff)->delete(route('safety.lototo.show', ['employee' => $this->colleague, 'board' => $this->board->id]))->assertStatus(405);

        $this->assertSame($before, [LototoActivity::query()->get()->toArray(), LototoBoardSlot::query()->count(), SpareLock::query()->count(), LototoBoard::query()->count()]);
        // The board page for this user has no editor and no management buttons.
        $board = $this->actingAs($this->staff)->get(route('safety.lototo.boards.show', $this->board))->assertOk()->getContent();
        foreach (['data-board-edit-start', 'data-editor-toolbar', route('safety.lototo.boards.index', ['board' => $this->board->id])] as $managerOnly) {
            $this->assertStringNotContainsString($managerOnly, $board);
        }
    }

    private function employee(string $number, string $name, ?int $userId = null, ?int $unitId = null): Employee
    {
        return Employee::factory()->create(['business_unit_id' => $unitId ?? $this->unit->id, 'employment_status_id' => $this->active->id, 'lototo_number' => $number, 'full_name' => $name, 'user_id' => $userId]);
    }
}
