<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\LototoActivity;
use App\Models\LototoBoard;
use App\Models\SpareLock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Outstanding/handover keeps the personal activity as its own record and continues on a separate spare record. */
class LototoHandoverRecordsTest extends TestCase
{
    use RefreshDatabase;

    private LototoBoard $board;
    private User $admin;
    private Employee $technician;
    private SpareLock $sp1;
    private SpareLock $sp2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $unit = BusinessUnit::factory()->create(['name' => 'Half Product']);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $unit->id, 'division_name' => 'Maintenance', 'code' => 'board-handover',
            'title' => 'MAINTENANCE · HALF PRODUCT', 'subtitle' => 'Test', 'layout' => ['board_columns' => 20],
            'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        $this->technician = Employee::factory()->create(['business_unit_id' => $unit->id, 'lototo_number' => '01']);
        $this->sp1 = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-01', 'is_active' => true]);
        $this->sp2 = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-02', 'is_active' => true]);
    }

    public function test_outstanding_keeps_the_personal_record_and_starts_a_separate_linked_spare_record(): void
    {
        $personal = $this->personalIn($this->technician);
        $this->travel(5)->minutes();
        $this->outstanding($this->technician, $this->sp1);

        $personal->refresh();
        $this->assertSame(['completed', 'outstanding'], [$personal->status, $personal->outcome]);
        $this->assertNull($personal->spare_lock_id);
        $this->assertNotNull($personal->ended_at);

        $spare = LototoActivity::query()->findOrFail(data_get($personal->out_details, 'spare_activity_id'));
        $this->assertNotSame($personal->id, $spare->id);
        $this->assertSame($this->sp1->id, $spare->spare_lock_id);
        $this->assertSame($personal->id, data_get($spare->out_details, 'handover_from_activity_id'));
        $this->assertSame([$this->technician->id, $this->board->code, 'in_progress', null], [$spare->employee_id, $spare->board, $spare->status, $spare->outcome]);
        $this->assertSame(['Mesin 3', 'Ganti bearing', ['kesetrum']], [$spare->location, $spare->activity, $spare->risks]);
        $this->assertNotSame('pending', $spare->supervisor_follow_up_status);
        // The spare starts at the handover, not when the personal lock went IN.
        $this->assertTrue($spare->started_at->greaterThan($personal->started_at));
        $this->assertEquals($personal->ended_at->timestamp, $spare->started_at->timestamp);
        $this->assertSame($spare->id, $this->sp1->fresh()->current_activity_id);

        // Spare QR shows its OUT form; OUT closes the spare record only.
        $this->actingAs($this->admin)->get(route('safety.lototo.form', ['spare_lock' => 'SP-01', 'board' => $this->board->id]))
            ->assertOk()->assertSee('OUT — aktivitas mulai')->assertSee('Close / Finish (OUT)');
        $personalBefore = $personal->fresh()->getAttributes();
        $this->travel(30)->minutes();
        $this->spareOut($this->technician, $this->sp1);

        $spare->refresh();
        $this->assertSame(['completed', 'finish'], [$spare->status, $spare->outcome]);
        $this->assertNotNull($spare->ended_at);
        $this->assertNotNull(data_get($spare->out_details, 'spare_ended_at'));
        $this->assertNull($this->sp1->fresh()->current_activity_id);
        $this->assertSame($personalBefore, $personal->fresh()->getAttributes());
        $this->assertSame(2, LototoActivity::query()->count());
    }

    public function test_technician_history_shows_the_personal_record_and_spare_history_the_spare_record(): void
    {
        $this->personalIn($this->technician, ['location' => 'Lokasi pribadi']);
        $this->outstanding($this->technician, $this->sp1);
        $this->spareOut($this->technician, $this->sp1);

        $technicianPage = $this->actingAs($this->admin)->get(route('safety.lototo.show', ['employee' => $this->technician, 'board' => $this->board->id]))->assertOk();
        $technicianPage->assertSee('Lokasi pribadi')->assertSee('CLOSE');
        // The record is listed once in the desktop table and once as a phone / tablet card, never twice in either.
        $this->assertSame([1, 1], $this->listedIn($technicianPage->getContent(), 'Lokasi pribadi'));

        $sparePage = $this->actingAs($this->admin)->get(route('safety.lototo.spare-locks.show', $this->sp1))->assertOk();
        $this->assertSame([1, 1], $this->listedIn($sparePage->getContent(), 'Lokasi pribadi'));
        $this->assertSame(1, $this->sp1->activities()->count());
        $this->assertNull($this->sp1->activities()->first()->out_details['spare_activity_id'] ?? null);
    }

    public function test_supervisor_outstanding_also_gets_two_records(): void
    {
        $supervisor = Employee::factory()->create(['business_unit_id' => $this->board->business_unit_id, 'lototo_number' => '101']);
        $personal = $this->personalIn($supervisor);
        $this->outstanding($supervisor, $this->sp1);
        $this->spareOut($supervisor, $this->sp1);

        $personal->refresh();
        $spare = LototoActivity::query()->findOrFail(data_get($personal->out_details, 'spare_activity_id'));
        $this->assertSame(['outstanding', null], [$personal->outcome, $personal->spare_lock_id]);
        $this->assertSame([$supervisor->id, $this->sp1->id, 'finish'], [$spare->employee_id, $spare->spare_lock_id, $spare->outcome]);
    }

    public function test_standalone_spare_in_keeps_its_own_record_and_never_attaches_to_a_handed_over_activity(): void
    {
        $personal = $this->personalIn($this->technician);
        $this->outstanding($this->technician, $this->sp1);
        $personalAfterHandover = $personal->fresh()->getAttributes();

        // A second spare for the same LOTOTO: its own spare-only record, the handed-over personal record is left alone.
        $this->actingAs($this->admin)->post(route('safety.lototo.form.store'), [...$this->answers($this->technician, 'in'),
            'lock_mode' => 'spare', 'spare_lock_id' => $this->sp2->id, 'location' => 'Panel 2',
        ])->assertSessionHasNoErrors();
        $second = LototoActivity::query()->where('spare_lock_id', $this->sp2->id)->sole();
        $this->assertTrue((bool) data_get($second->out_details, 'spare_only'));
        $this->assertNull(data_get($second->out_details, 'handover_from_activity_id'));
        $this->assertSame('Panel 2', $second->location);
        $this->assertSame($personalAfterHandover, $personal->fresh()->getAttributes());

        // Each spare has its own history: closing SP-02 leaves SP-01's record running.
        $this->spareOut($this->technician, $this->sp2);
        $first = LototoActivity::query()->where('spare_lock_id', $this->sp1->id)->sole();
        $this->assertSame('completed', $second->fresh()->status);
        $this->assertSame('in_progress', $first->status);
        $this->assertSame($first->id, $this->sp1->fresh()->current_activity_id);
        $this->spareOut($this->technician, $this->sp1);
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame(3, LototoActivity::query()->count());
        $this->assertSame($personalAfterHandover, $personal->fresh()->getAttributes());
    }

    public function test_a_spare_is_closed_only_by_its_holder_or_an_administrator(): void
    {
        $holderUser = User::factory()->create();
        $holderUser->assignRole(RoleName::MaintenanceStaff->value);
        $this->technician->update(['user_id' => $holderUser->id]);
        $otherUser = User::factory()->create();
        $otherUser->assignRole(RoleName::MaintenanceStaff->value);
        $other = Employee::factory()->create(['business_unit_id' => $this->board->business_unit_id, 'lototo_number' => '02', 'user_id' => $otherUser->id]);

        $this->personalIn($this->technician);
        $this->outstanding($this->technician, $this->sp1);
        $spare = LototoActivity::query()->where('spare_lock_id', $this->sp1->id)->sole();

        $this->actingAs($otherUser)->get(route('safety.lototo.form', ['spare_lock' => 'SP-01', 'board' => $this->board->id]))->assertForbidden();
        // Under their own number they hold no activity on this spare, so the OUT is refused.
        $this->actingAs($otherUser)->post(route('safety.lototo.form.store'), $this->spareOutData($other, $this->sp1))->assertSessionHasErrors('spare_lock_id');
        $this->actingAs($otherUser)->post(route('safety.lototo.form.store'), $this->spareOutData($this->technician, $this->sp1))->assertForbidden();
        $this->assertSame('in_progress', $spare->fresh()->status);

        $this->actingAs($holderUser)->get(route('safety.lototo.form', ['spare_lock' => 'SP-01', 'board' => $this->board->id]))->assertOk();
        $this->actingAs($holderUser)->post(route('safety.lototo.form.store'), $this->spareOutData($this->technician, $this->sp1))->assertSessionHasNoErrors();
        $this->assertSame('completed', $spare->fresh()->status);
    }

    public function test_outstanding_saved_as_one_combined_record_still_shows_in_both_histories_and_can_be_closed(): void
    {
        // Shape of the outstanding records saved before this change: the personal record itself carries the spare.
        $legacy = LototoActivity::query()->create($this->recordAttributes($this->technician, [
            'location' => 'Lama gabungan', 'spare_lock_id' => $this->sp1->id, 'status' => 'completed', 'outcome' => 'outstanding',
            'out_details' => ['spare_lock_code' => 'SP-01', 'spare_started_at' => now()->subHour()->toISOString()],
            'started_at' => now()->subHours(2), 'ended_at' => now()->subHour(),
        ]));
        $this->sp1->update(['current_activity_id' => $legacy->id]);
        $count = LototoActivity::query()->count();

        foreach (range(1, 2) as $visit) {
            $this->actingAs($this->admin)->get(route('safety.lototo.show', ['employee' => $this->technician, 'board' => $this->board->id]))->assertOk()->assertSee('Lama gabungan');
            $this->actingAs($this->admin)->get(route('safety.lototo.spare-locks.show', $this->sp1))->assertOk()->assertSee('Lama gabungan');
        }
        $this->assertSame($count, LototoActivity::query()->count());

        // Closing the spare of a combined record keeps working the old way and the record stays personal history.
        $this->spareOut($this->technician, $this->sp1);
        $legacy->refresh();
        $this->assertSame(['completed', 'outstanding', $this->sp1->id], [$legacy->status, $legacy->outcome, $legacy->spare_lock_id]);
        $this->assertNotNull(data_get($legacy->out_details, 'spare_ended_at'));
        $this->assertNull($this->sp1->fresh()->current_activity_id);
        $this->assertSame($count, LototoActivity::query()->count());
        $this->actingAs($this->admin)->get(route('safety.lototo.show', ['employee' => $this->technician, 'board' => $this->board->id]))->assertSee('Lama gabungan');
    }

    public function test_an_old_outstanding_never_handed_to_a_spare_continues_on_a_new_linked_spare_record(): void
    {
        $pending = LototoActivity::query()->create($this->recordAttributes($this->technician, [
            'location' => 'Belum diteruskan', 'status' => 'completed', 'outcome' => 'outstanding', 'started_at' => now()->subHours(2), 'ended_at' => now()->subHour(),
        ]));

        $this->actingAs($this->admin)->post(route('safety.lototo.form.store'), $this->answers($this->technician, 'in') + [
            'lock_mode' => 'spare', 'spare_lock_id' => $this->sp1->id,
        ])->assertSessionHasNoErrors();

        $spare = LototoActivity::query()->where('spare_lock_id', $this->sp1->id)->sole();
        $this->assertNotSame($pending->id, $spare->id);
        $this->assertSame($pending->id, data_get($spare->out_details, 'handover_from_activity_id'));
        $this->assertSame('Belum diteruskan', $spare->location);
        $this->assertSame($spare->id, data_get($pending->fresh()->out_details, 'spare_activity_id'));
        $this->assertNull($pending->fresh()->spare_lock_id);
        $this->assertSame($spare->id, $this->sp1->fresh()->current_activity_id);
    }

    /** [rows of the desktop table, phone / tablet cards] that show $location. */
    private function listedIn(string $html, string $location): array
    {
        $table = substr($html, strpos($html, '<table'), strpos($html, '</table>') - strpos($html, '<table'));

        return [substr_count($table, $location), substr_count($html, 'aria-label="Aktivitas LOTOTO '.$location.'"')];
    }

    private function personalIn(Employee $employee, array $overrides = []): LototoActivity
    {
        $this->actingAs($this->admin)->post(route('safety.lototo.form.store'), [...$this->answers($employee, 'in'), ...$overrides])->assertSessionHasNoErrors();

        return LototoActivity::query()->where('employee_id', $employee->id)->whereNull('spare_lock_id')->latest('id')->firstOrFail();
    }

    private function outstanding(Employee $employee, SpareLock $spare): void
    {
        $this->actingAs($this->admin)->post(route('safety.lototo.form.store'), [
            'lototo_number' => $employee->lototo_number, 'employee_id' => $employee->id, 'board_id' => $this->board->id,
            'lock_status' => 'out', 'outcome' => 'outstanding', 'spare_lock_id' => $spare->id,
        ])->assertSessionHasNoErrors();
    }

    private function spareOut(Employee $employee, SpareLock $spare): void
    {
        $this->actingAs($this->admin)->post(route('safety.lototo.form.store'), $this->spareOutData($employee, $spare))->assertSessionHasNoErrors();
    }

    private function spareOutData(Employee $employee, SpareLock $spare): array
    {
        return ['lototo_number' => $employee->lototo_number, 'employee_id' => $employee->id, 'board_id' => $this->board->id,
            'lock_mode' => 'spare', 'lock_status' => 'out', 'outcome' => 'finish', 'spare_lock_id' => $spare->id];
    }

    private function answers(Employee $employee, string $status): array
    {
        return [
            'lototo_number' => $employee->lototo_number, 'employee_id' => $employee->id, 'board_id' => $this->board->id, 'lock_status' => $status,
            'location' => 'Mesin 3', 'activity' => 'Ganti bearing', 'risks' => ['kesetrum'], 'risk_controls' => 'Isolasi energi',
            'knows_procedure' => 'ya', 'follows_lototo' => 'ya', 'uses_ppe' => 'ya', 'safe_tools' => 'ya', 'safe_hands' => 'ya',
        ];
    }

    private function recordAttributes(Employee $employee, array $overrides): array
    {
        return [
            'employee_id' => $employee->id, 'board' => $this->board->code, 'activity' => 'Pekerjaan lama', 'risks' => ['terjepit'],
            'risk_controls' => 'Lock', 'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true, 'safe_tools' => true, 'safe_hands' => true,
            ...$overrides,
        ];
    }
}
