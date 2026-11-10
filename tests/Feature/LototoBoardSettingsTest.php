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
use Database\Seeders\SpareLockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LototoBoardSettingsTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $businessUnit;

    private EmploymentStatus $activeStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SpareLockSeeder::class);
        $this->businessUnit = BusinessUnit::factory()->create(['id' => 1]);
        $board = LototoBoard::query()->create([
            'business_unit_id' => $this->businessUnit->id,
            'division_name' => 'Maintenance',
            'code' => 'mtn_tr',
            'title' => 'MAINTENANCE · TIRE CORD',
            'subtitle' => 'Daftar LOTOTO karyawan dan spare',
            'layout' => ['technician_columns' => 20, 'supervisor_columns' => 10, 'section_order' => ['technician', 'other', 'spare']],
            'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true],
            'is_active' => true,
        ]);
        LototoBoardSlot::query()->whereNull('board_id')->update(['board_id' => $board->id]);
        SpareLock::query()->whereNull('board_id')->update(['board_id' => $board->id]);
        $this->activeStatus = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
    }

    public function test_mtn_tr_board_shows_the_technician_saved_on_each_slot_and_their_status_moves_with_them(): void
    {
        $admin = $this->administrator();
        $first = $this->employee('22');
        $duplicate = $this->employee('22');
        $lastNumber = $this->employee('66');
        $activity = tap($this->activity($lastNumber))->update(['board' => 'mtn_tr']);

        $this->actingAs($admin);
        $this->slot('technician', 22)->update(['employee_id' => $first->id]);
        $this->slot('technician', 23)->update(['employee_id' => $duplicate->id]);
        $this->slot('technician', 50)->update(['employee_id' => $lastNumber->id]);

        // Both employees sharing LOTOTO 22 are drawn on their own slots; LOTOTO 66 sits on slot 50 with its IN status.
        $this->assertSame([
            22 => [$first->full_name.' LOTOTO 22, status OUT'],
            23 => [$duplicate->full_name.' LOTOTO 22, status OUT'],
            50 => [$lastNumber->full_name.' LOTOTO 66, status IN'],
        ], $this->technicianCards());

        // Moving the employee moves the card, with the same status, history link and nothing left behind.
        $activity->update(['status' => 'completed', 'outcome' => 'finish', 'ended_at' => now()]);
        $this->slot('technician', 50)->update(['employee_id' => null]);
        $this->slot('technician', 2)->update(['employee_id' => $lastNumber->id]);

        $cards = $this->technicianCards();
        $this->assertSame([$lastNumber->full_name.' LOTOTO 66, status OUT'], $cards[2]);
        $this->assertArrayNotHasKey(50, $cards);
        $this->get(route('safety.lototo.boards.show', $this->board()))
            ->assertSee('TECHNICIAN posisi 50 kosong')
            ->assertSee(route('safety.lototo.show', ['employee' => $lastNumber, 'board' => $this->board()->id]), false);
    }

    public function test_board_has_indexed_supervisor_slots_and_fifteen_other_items_in_one_row(): void
    {
        $admin = $this->administrator();

        $response = $this->actingAs($admin)->get(route('safety.lototo.boards.show', $this->board()));

        $response->assertOk()
            ->assertSee('SUPERVISOR posisi 10 kosong', false)
            ->assertSee('SUPERVISOR', false)
            ->assertSee('Tool 15')
            ->assertSee('lototo-technician-grid', false)
            ->assertSee('lototo-supervisor-grid', false)
            ->assertSee('--lototo-section-columns: 20;', false)
            ->assertSee('--lototo-supervisor-columns: 10;', false)
            ->assertSee('--lototo-board-columns: 20;', false)
            ->assertSee('repeating-linear-gradient(135deg, #171717 0, #171717 7px, #facc15 7px, #facc15 14px)', false)
            ->assertSee('TECHNICIAN')
            ->assertSee('SUPERVISOR')
            ->assertSee('grid-column: 10; grid-row: 1;', false)
            ->assertSee('grid-column: 1; grid-row: 1;', false)
            ->assertSee('>01</span>', false)
            ->assertSee('>10</span>', false)
            ->assertSee('>15</span>', false)
            ->assertSee('grid-column: 1 / span 1;', false)
            ->assertSee('data-slot-category="tool" data-slot-position="15"', false);
        $this->assertSame(15, substr_count($response->getContent(), '>Tool '));
        $this->assertMatchesRegularExpression(
            '/lototo-supervisor-group.*?grid-row: 1 \/ span 1;.*?lototo-supervisor-grid.*?grid-column: 1; grid-row: 1;.*?SUPERVISOR posisi 1 kosong/s',
            $response->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/lototo-supervisor-grid.*?grid-column: 1; grid-row: 1;.*?SUPERVISOR posisi 10 kosong/s',
            $response->getContent(),
        );
    }

    public function test_board_shows_empty_other_section_when_no_tool_slots_are_saved(): void
    {
        $admin = $this->administrator();
        LototoBoardSlot::query()->where('board_id', $this->board()->id)->where('category', 'tool')->delete();

        $response = $this->actingAs($admin)->get(route('safety.lototo.boards.show', $this->board()));

        $response->assertOk()
            ->assertSee('Belum ada data LOTOTO untuk bagian ini.');
    }

    public function test_non_admin_cannot_open_or_submit_spare_in_out_forms(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        Employee::factory()->forUser($staff)->create([
            'business_unit_id' => $this->businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '01',
        ]);
        $spareLock = SpareLock::query()->where('code', 'SP-01')->firstOrFail();

        $this->actingAs($staff)
            ->get(route('safety.lototo.form', ['spare_lock' => $spareLock->code]))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('safety.lototo.spare-locks.show', $spareLock))
            ->assertOk()
            ->assertDontSee('Form IN / Mulai')
            ->assertDontSee('Form OUT / CLOSE');

        $this->actingAs($staff)
            ->post(route('safety.lototo.form.store'), [
                'lock_mode' => 'spare',
                'lock_status' => 'in',
            ])
            ->assertForbidden();
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);

        return $user;
    }

    private function board(): LototoBoard
    {
        return LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail();
    }

    /** Technician cards on the board, keyed by the slot index printed on them. */
    private function technicianCards(): array
    {
        $html = $this->get(route('safety.lototo.boards.show', $this->board()))->assertOk()->getContent();
        preg_match('/lototo-technician-grid.*?<\/section>/s', $html, $grid);
        preg_match_all('/aria-label="([^"]*LOTOTO \d+, status (?:IN|OUT)[^"]*)".*?lototo-position-index">(\d+)</s', $grid[0], $cards, PREG_SET_ORDER);

        return collect($cards)->groupBy(fn ($card) => (int) $card[2])->map(fn ($group) => $group->pluck(1)->map(fn ($label) => html_entity_decode($label, ENT_QUOTES))->all())->all();
    }

    private function employee(string $lototoNumber): Employee
    {
        return Employee::factory()->create([
            'business_unit_id' => $this->businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => $lototoNumber,
        ]);
    }

    private function slot(string $category, int $position): LototoBoardSlot
    {
        return LototoBoardSlot::query()
            ->where('category', $category)
            ->where('position', $position)
            ->firstOrFail();
    }


    private function activity(Employee $employee): LototoActivity
    {
        return LototoActivity::query()->create([
            'employee_id' => $employee->id,
            'board' => 'mtn_bpr',
            'location' => 'Test',
            'activity' => 'Test activity',
            'risks' => ['terjepit'],
            'risk_controls' => 'Test controls',
            'knows_procedure' => true,
            'follows_lototo' => true,
            'uses_ppe' => true,
            'safe_tools' => true,
            'safe_hands' => true,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }
}
