<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ShiftComm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftCommTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staff(string $role = 'Maintenance Staff'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role === 'Maintenance Staff' ? RoleName::MaintenanceStaff->value : $role);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'comm_date' => '2026-10-01',
            'shift' => 1,
            'team' => 'A',
            'machine_no' => 'DR-12',
            'problem' => 'Die broken',
            'is_torsion_shaft' => 0,
        ];
    }

    public function test_numbers_follow_date_shift_and_reset_per_shift_and_date(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload())->assertRedirect();
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload())->assertRedirect();
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['shift' => 2]))->assertRedirect();
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['comm_date' => '2026-10-02']))->assertRedirect();

        $this->assertSame(
            ['261001101', '261001102', '261001201', '261002101'],
            ShiftComm::orderBy('id')->pluck('comm_number')->all()
        );
    }

    public function test_default_shift_by_time(): void
    {
        $this->assertSame(1, ShiftComm::defaultShiftFor(Carbon::parse('2026-10-01 08:00')));
        $this->assertSame(1, ShiftComm::defaultShiftFor(Carbon::parse('2026-10-01 15:59')));
        $this->assertSame(2, ShiftComm::defaultShiftFor(Carbon::parse('2026-10-01 16:00')));
        $this->assertSame(2, ShiftComm::defaultShiftFor(Carbon::parse('2026-10-01 23:59')));
        $this->assertSame(3, ShiftComm::defaultShiftFor(Carbon::parse('2026-10-01 00:00')));
        $this->assertSame(3, ShiftComm::defaultShiftFor(Carbon::parse('2026-10-01 07:59')));
    }

    public function test_only_author_or_admin_can_edit_and_date_shift_stay_fixed(): void
    {
        $author = $this->staff();
        $other = $this->staff();
        $this->actingAs($author)->post(route('shift-comm.store'), $this->payload(['length_m' => 12]));
        $comm = ShiftComm::firstOrFail();

        $this->actingAs($other)->get(route('shift-comm.show', $comm))->assertOk();
        $this->actingAs($other)->put(route('shift-comm.update', $comm), $this->payload())->assertForbidden();

        $this->actingAs($author)->put(route('shift-comm.update', $comm), $this->payload(['comm_date' => '2026-12-12', 'shift' => 3, 'problem' => 'Fixed']))->assertRedirect();
        $comm->refresh();
        $this->assertSame('Fixed', $comm->problem);
        $this->assertSame('2026-10-01', $comm->comm_date->toDateString());
        $this->assertSame(1, $comm->shift);
        $this->assertSame('261001101', $comm->comm_number);
    }

    public function test_construction_suggestions_come_from_history(): void
    {
        $user = $this->staff();
        foreach (['7x7 0.20', '7x7 0.20', '3+9 0.175'] as $c) {
            $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['construction' => $c]));
        }

        $this->assertSame(['7x7 0.20', '3+9 0.175'], ShiftComm::constructionSuggestions());
        $this->actingAs($user)->get(route('shift-comm.create'))->assertOk()->assertSee('3+9 0.175');
        $this->actingAs($user)->get(route('shift-comm.index'))->assertOk()->assertSee('261001101');
    }

    public function test_torsion_shaft_answer_controls_the_estafet_status(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['is_torsion_shaft' => 1]))
            ->assertSessionHasErrors('torsion_relay_status');
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['is_torsion_shaft' => 1, 'torsion_relay_status' => 'next_shift']))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['is_torsion_shaft' => 0, 'torsion_relay_status' => 'done']))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['is_torsion_shaft' => null]))
            ->assertSessionHasErrors('is_torsion_shaft');

        $rows = ShiftComm::orderBy('id')->get();
        $this->assertSame('next_shift', $rows[0]->torsion_relay_status);
        $this->assertTrue($rows[0]->is_torsion_shaft);
        $this->assertNull($rows[1]->torsion_relay_status);
        $this->assertFalse($rows[1]->is_torsion_shaft);
    }

    public function test_index_filters_by_torsion_shaft_and_estafet_status(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['machine_no' => 'M-NO']));
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['machine_no' => 'M-DONE', 'is_torsion_shaft' => 1, 'torsion_relay_status' => 'done']));
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['machine_no' => 'M-NEXT', 'is_torsion_shaft' => 1, 'torsion_relay_status' => 'next_shift']));

        $this->actingAs($user)->get(route('shift-comm.index', ['torsion' => 1]))
            ->assertSee('M-DONE')->assertSee('M-NEXT')->assertDontSee('M-NO');
        $this->actingAs($user)->get(route('shift-comm.index', ['torsion' => 0]))
            ->assertSee('M-NO')->assertDontSee('M-DONE');
        $this->actingAs($user)->get(route('shift-comm.index', ['relay' => 'next_shift']))
            ->assertSee('M-NEXT')->assertDontSee('M-DONE')->assertDontSee('M-NO');
    }

    public function test_repeat_torsion_shaft_warning_rolls_30_days_from_latest_record(): void
    {
        $user = $this->staff();
        $torsion = fn (string $machine, string $date, int $flag = 1) => ShiftComm::createNumbered([
            'comm_date' => $date, 'shift' => 1, 'team' => 'A', 'machine_no' => $machine, 'problem' => 'x',
            'is_torsion_shaft' => $flag, 'torsion_relay_status' => $flag ? 'done' : null, 'created_by' => $user->id,
        ]);

        // M-001 / m001 / M 001 are one machine: 4 within 30 days of 2026-10-01 (the 2026-08-30 one is too old).
        foreach ([['M-001', '2026-08-30'], ['m001', '2026-09-05'], ['M 001', '2026-09-20'], ['M-001', '2026-09-28'], ['M-001', '2026-10-01']] as [$m, $d]) {
            $torsion($m, $d);
        }
        // Only 3 torsion + non-torsion records: no warning.
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $d) { $torsion('M-002', $d); }
        foreach (['2026-09-04', '2026-09-05'] as $d) { $torsion('M-002', $d, 0); }
        // 5 on another machine sorts first.
        foreach (['2026-09-27', '2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01'] as $d) { $torsion('M-003', $d); }

        $this->travelTo('2026-10-01 10:00'); // still Shift 1 of the latest record's day
        $warnings = ShiftComm::repeatTorsionWarnings();

        $this->assertCount(2, $warnings);
        $this->assertSame(['M-003', 5], [$warnings[0]['machine'], $warnings[0]['count']]);
        $this->assertSame(['M-001', 4], [$warnings[1]['machine'], $warnings[1]['count']]);
        $this->assertSame('2026-09-05', $warnings[1]['first']->toDateString());
        $this->assertSame('2026-10-01', $warnings[1]['last']->toDateString());
        $this->actingAs($user)->get(route('shift-comm.index'))->assertSee('Mesin M-001 telah mengalami Problem Torsion Shaft sebanyak 4 kali', false);
    }

    public function test_repeat_warning_clears_when_next_shift_starts_without_a_new_torsion_record(): void
    {
        $user = $this->staff();
        foreach (['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01'] as $d) {
            ShiftComm::createNumbered([
                'comm_date' => $d, 'shift' => 2, 'team' => 'A', 'machine_no' => 'M-9', 'problem' => 'x',
                'is_torsion_shaft' => 1, 'torsion_relay_status' => 'done', 'created_by' => $user->id,
            ]);
        }

        // Shift 2 of 2026-10-01 is the latest record: warning stays through that shift...
        $this->assertCount(1, ShiftComm::repeatTorsionWarnings(Carbon::parse('2026-10-01 20:00')));
        // ...and clears when Shift 3 starts (00:00 next day) with no new Torsion Shaft record.
        $this->assertCount(0, ShiftComm::repeatTorsionWarnings(Carbon::parse('2026-10-02 00:30')));

        // A new Torsion Shaft record in that shift brings it back.
        ShiftComm::createNumbered([
            'comm_date' => '2026-10-02', 'shift' => 3, 'team' => 'B', 'machine_no' => 'm9', 'problem' => 'x',
            'is_torsion_shaft' => 1, 'torsion_relay_status' => 'done', 'created_by' => $user->id,
        ]);
        $this->assertCount(1, ShiftComm::repeatTorsionWarnings(Carbon::parse('2026-10-02 03:00')));
    }

    public function test_validation_and_guest_block(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['machine_no' => '', 'length_m' => -1, 'team' => 'Z']))
            ->assertSessionHasErrors(['machine_no', 'length_m', 'team']);

        $guest = User::factory()->create();
        $guest->assignRole(RoleName::Guest->value);
        $this->actingAs($guest)->get(route('shift-comm.index'))->assertForbidden();
    }
}
