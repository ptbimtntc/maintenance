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

    public function test_only_administrator_can_see_delete_button_and_destroy_shift_comm(): void
    {
        $author = $this->staff();
        $admin = $this->staff(RoleName::Administrator->value);
        $other = $this->staff();

        $this->actingAs($author)->post(route('shift-comm.store'), $this->payload(['problem' => 'Test Delete']));
        $comm = ShiftComm::firstOrFail();

        // 1. Author can see Edit, but CANNOT see Delete button on show page
        $authorShow = $this->actingAs($author)->get(route('shift-comm.show', $comm));
        $authorShow->assertOk();
        $authorShow->assertSee('Edit');
        $authorShow->assertDontSee('>Delete</button>', false);

        // Author cannot delete via DELETE request
        $this->actingAs($author)->delete(route('shift-comm.destroy', $comm))->assertForbidden();

        // 2. Other non-admin staff cannot see Edit or Delete, and cannot delete
        $otherShow = $this->actingAs($other)->get(route('shift-comm.show', $comm));
        $otherShow->assertOk();
        $otherShow->assertDontSee('Edit');
        $otherShow->assertDontSee('>Delete</button>', false);
        $this->actingAs($other)->delete(route('shift-comm.destroy', $comm))->assertForbidden();

        // 3. Administrator CAN see Delete button and delete the entry
        $adminShow = $this->actingAs($admin)->get(route('shift-comm.show', $comm));
        $adminShow->assertOk();
        $adminShow->assertSee('Edit');
        $adminShow->assertSee('>Delete</button>', false);

        $this->actingAs($admin)->delete(route('shift-comm.destroy', $comm))
            ->assertRedirect(route('shift-comm.index'));
        $this->assertDatabaseMissing('shift_comms', ['id' => $comm->id]);
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
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['is_torsion_shaft' => 1, 'torsion_relay_status' => 'done']))
            ->assertSessionHasErrors('torsion_relay_status');
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['is_torsion_shaft' => 1, 'torsion_relay_status' => 'next_shift']))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['is_torsion_shaft' => 0, 'torsion_relay_status' => 'in_progress']))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['is_torsion_shaft' => null]))
            ->assertSessionHasErrors('is_torsion_shaft');

        $this->actingAs($user)->get(route('shift-comm.create'))
            ->assertOk()
            ->assertDontSee('>Selesai<', false);

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
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['machine_no' => 'M-PROG', 'is_torsion_shaft' => 1, 'torsion_relay_status' => 'in_progress']));
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload(['machine_no' => 'M-NEXT', 'is_torsion_shaft' => 1, 'torsion_relay_status' => 'next_shift']));

        $this->actingAs($user)->get(route('shift-comm.index', ['torsion' => 1]))
            ->assertSee('M-PROG')->assertSee('M-NEXT')->assertDontSee('M-NO');
        $this->actingAs($user)->get(route('shift-comm.index', ['torsion' => 0]))
            ->assertSee('M-NO')->assertDontSee('M-PROG');
        $this->actingAs($user)->get(route('shift-comm.index', ['relay' => 'next_shift']))
            ->assertSee('M-NEXT')->assertDontSee('M-PROG')->assertDontSee('M-NO');
    }

    public function test_repeat_torsion_shaft_warning_rolls_7_days_from_latest_record(): void
    {
        $user = $this->staff();
        $torsion = fn (string $machine, string $date, int $flag = 1) => ShiftComm::createNumbered([
            'comm_date' => $date, 'shift' => 1, 'team' => 'A', 'machine_no' => $machine, 'problem' => 'x',
            'is_torsion_shaft' => $flag, 'torsion_relay_status' => $flag ? 'in_progress' : null, 'created_by' => $user->id,
        ]);

        // M-001 / m001 / M 001 are one machine: 4 within 7 days of 2026-10-01 (the 2026-09-20 one is too old).
        foreach ([['M-001', '2026-09-20'], ['m001', '2026-09-25'], ['M 001', '2026-09-26'], ['M-001', '2026-09-28'], ['M-001', '2026-10-01']] as [$m, $d]) {
            $torsion($m, $d);
        }
        // Only 3 torsion + non-torsion records: no warning.
        foreach (['2026-09-26', '2026-09-27', '2026-09-28'] as $d) { $torsion('M-002', $d); }
        foreach (['2026-09-29', '2026-09-30'] as $d) { $torsion('M-002', $d, 0); }
        // 5 on another machine sorts first.
        foreach (['2026-09-27', '2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01'] as $d) { $torsion('M-003', $d); }

        $this->travelTo('2026-10-01 10:00'); // still Shift 1 of the latest record's day
        $warnings = ShiftComm::repeatTorsionWarnings();

        $this->assertCount(2, $warnings);
        $this->assertSame(['M-003', 5], [$warnings[0]['machine'], $warnings[0]['count']]);
        $this->assertSame(['M-001', 4], [$warnings[1]['machine'], $warnings[1]['count']]);
        $this->assertSame('2026-09-25', $warnings[1]['first']->toDateString());
        $this->assertSame('2026-10-01', $warnings[1]['last']->toDateString());
        $this->assertCount(5, $warnings[0]['records']);
        $this->assertCount(4, $warnings[1]['records']);
        $this->actingAs($user)->get(route('shift-comm.index'))
            ->assertSee('Peringatan Problem Berulang TS (Rolling 7 hari)')
            ->assertSee('Mesin-mesin di bawah ini telah mengalami problem Torsion Shaft berulang dalam 7 hari terakhir', false)
            ->assertSee('• Mesin M-001 (4x Problem)', false)
            ->assertSee('• Mesin M-003 (5x Problem)', false)
            ->assertSee('(25 Sep 2026 s/d 01 Oct 2026)')
            ->assertSee('Lihat di tabel')
            ->assertDontSee('M-002 (', false);

        $filtered = $this->actingAs($user)->get(route('shift-comm.index', ['q' => 'M-001', 'torsion' => '1']));
        $filtered->assertOk()
            ->assertSee('M-001');
    }

    public function test_warning_component_hidden_when_no_repeated_problems(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->get(route('shift-comm.index'))
            ->assertOk()
            ->assertDontSee('Peringatan Problem Berulang TS');
    }

    public function test_dynamic_rolling_days_setting_reflected_in_view(): void
    {
        $user = $this->staff();
        \App\Models\Setting::set('shift_comm_repeat_window_days', '14');

        foreach (['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01'] as $d) {
            ShiftComm::createNumbered([
                'comm_date' => $d, 'shift' => 1, 'team' => 'A', 'machine_no' => 'M-DYN', 'problem' => 'x',
                'is_torsion_shaft' => 1, 'torsion_relay_status' => 'in_progress', 'created_by' => $user->id,
            ]);
        }

        $this->travelTo('2026-10-01 10:00');
        $this->actingAs($user)->get(route('shift-comm.index'))
            ->assertSee('Peringatan Problem Berulang TS (Rolling 14 hari)')
            ->assertSee('dalam 14 hari terakhir', false);
    }

    public function test_repeat_warning_clears_when_next_shift_starts_without_a_new_torsion_record(): void
    {
        $user = $this->staff();
        foreach (['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01'] as $d) {
            ShiftComm::createNumbered([
                'comm_date' => $d, 'shift' => 2, 'team' => 'A', 'machine_no' => 'M-9', 'problem' => 'x',
                'is_torsion_shaft' => 1, 'torsion_relay_status' => 'in_progress', 'created_by' => $user->id,
            ]);
        }

        // Shift 2 of 2026-10-01 is the latest record: warning stays through that shift...
        $this->assertCount(1, ShiftComm::repeatTorsionWarnings(Carbon::parse('2026-10-01 20:00')));
        // ...and clears when Shift 3 starts (00:00 next day) with no new Torsion Shaft record.
        $this->assertCount(0, ShiftComm::repeatTorsionWarnings(Carbon::parse('2026-10-02 00:30')));

        // A new Torsion Shaft record in that shift brings it back.
        ShiftComm::createNumbered([
            'comm_date' => '2026-10-02', 'shift' => 3, 'team' => 'B', 'machine_no' => 'm9', 'problem' => 'x',
            'is_torsion_shaft' => 1, 'torsion_relay_status' => 'in_progress', 'created_by' => $user->id,
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

    public function test_machine_type_auto_fill_and_persistence(): void
    {
        $this->seed(\Database\Seeders\MachineSeeder::class);

        $user = $this->staff();

        // 1. Direct submit with machine_type
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload([
            'machine_no' => 'GL-01',
            'machine_type' => 'Galvanizing Line',
        ]))->assertRedirect();

        $comm1 = ShiftComm::where('machine_no', 'GL-01')->firstOrFail();
        $this->assertSame('Galvanizing Line', $comm1->machine_type);

        // 2. Fallback auto-detection from catalog or prefix
        $this->actingAs($user)->post(route('shift-comm.store'), $this->payload([
            'machine_no' => 'DR-12',
            'machine_type' => null,
        ]))->assertRedirect();

        $comm2 = ShiftComm::where('machine_no', 'DR-12')->firstOrFail();
        $this->assertSame('Drawing Machine', $comm2->machine_type);

        // 3. Verification on index and show pages
        $this->actingAs($user)->get(route('shift-comm.show', $comm1))
            ->assertOk()
            ->assertSee('Galvanizing Line')
            ->assertSee('Machine Type');

        $this->actingAs($user)->get(route('shift-comm.index'))
            ->assertOk()
            ->assertSee('Galvanizing Line')
            ->assertSee('Drawing Machine');

        // 4. Test database seeded machine lookup
        $this->assertSame('BF OLD', ShiftComm::lookupMachineType('1001'));
        $this->assertSame('BF2003', ShiftComm::lookupMachineType('1032'));
        $this->assertSame('HKM2009', ShiftComm::lookupMachineType('5000'));
        $this->assertSame('HENXIN2009', ShiftComm::lookupMachineType('1329'));

        // 5. Test fallback prefix detection
        $this->assertSame('Drawing Machine', ShiftComm::lookupMachineType('DR-05'));
        $this->assertSame('Drawing Machine', ShiftComm::lookupMachineType('drw-99'));
        $this->assertSame('Galvanizing Line', ShiftComm::lookupMachineType('gl-02'));
        $this->assertSame('Stranding Machine', ShiftComm::lookupMachineType('ST-01'));
        $this->assertSame('Tubular Strander', ShiftComm::lookupMachineType('TB-02'));
        $this->assertNull(ShiftComm::lookupMachineType('UNKNOWN-99'));
        $this->assertNull(ShiftComm::lookupMachineType(''));
        $this->assertNull(ShiftComm::lookupMachineType(null));

        // 6. Test async lookup JSON endpoint
        $this->actingAs($user)->getJson(route('shift-comm.machine-lookup', ['no' => '1001']))
            ->assertOk()
            ->assertJson([
                'machine_no' => '1001',
                'machine_type' => 'BF OLD',
            ]);
    }

    private function xlsx(array $rows): \Illuminate\Http\UploadedFile
    {
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'comm').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($path);

        return new \Illuminate\Http\UploadedFile($path, 'shift-comm.xlsx', null, null, true);
    }

    public function test_only_administrator_sees_import_and_export_buttons(): void
    {
        $admin = $this->staff(RoleName::Administrator->value);
        $staff = $this->staff();

        $this->actingAs($admin)->get(route('shift-comm.index'))
            ->assertOk()
            ->assertSee('Export XLSX')
            ->assertSee('Import XLSX');

        $this->actingAs($staff)->get(route('shift-comm.index'))
            ->assertOk()
            ->assertDontSee('Export XLSX')
            ->assertDontSee('Import XLSX');
    }

    public function test_only_administrator_can_export_shift_comm(): void
    {
        $admin = $this->staff(RoleName::Administrator->value);
        $staff = $this->staff();

        ShiftComm::createNumbered([
            'comm_date' => '2026-10-01',
            'shift' => 1,
            'team' => 'A',
            'machine_no' => 'M-EXP',
            'problem' => 'Test export problem',
            'is_torsion_shaft' => 0,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($staff)->get(route('shift-comm.export'))
            ->assertForbidden();

        $response = $this->actingAs($admin)->get(route('shift-comm.export'));
        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $response->headers->get('content-type'));
    }

    public function test_only_administrator_can_import_shift_comm(): void
    {
        $admin = $this->staff(RoleName::Administrator->value);
        $staff = $this->staff();

        $file = $this->xlsx([
            ['Date', 'Shift', 'Team', 'Machine No.', 'Machine Type', 'Problem', 'Torsion Shaft', 'Estafet Torsion Shaft'],
            ['2026-10-05', '1', 'B', 'M-IMP-01', 'BF2008', 'Wire cut', 'YA', 'Sedang dikerjakan'],
            ['2026-10-05', '2', 'C', 'M-IMP-02', 'BF OLD', 'Motor overheat', 'TIDAK', null],
        ]);

        $this->actingAs($staff)->post(route('shift-comm.import'), ['file' => $file])
            ->assertForbidden();

        $response = $this->actingAs($admin)->post(route('shift-comm.import'), ['file' => $file]);
        $response->assertRedirect(route('shift-comm.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('shift_comms', [
            'machine_no' => 'M-IMP-01',
            'problem' => 'Wire cut',
            'is_torsion_shaft' => 1,
            'torsion_relay_status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('shift_comms', [
            'machine_no' => 'M-IMP-02',
            'problem' => 'Motor overheat',
            'is_torsion_shaft' => 0,
        ]);
    }

    public function test_edit_button_hidden_and_update_blocked_after_grace_period(): void
    {
        $author = $this->staff();
        $admin = $this->staff(RoleName::Administrator->value);

        // 1. Record created 25 hours ago (past the default 24-hour grace period)
        $this->actingAs($author)->post(route('shift-comm.store'), $this->payload(['problem' => 'Old record']));
        $oldComm = ShiftComm::latest('id')->firstOrFail();
        $oldComm->created_at = now()->subHours(25);
        $oldComm->save();

        // Author cannot see edit button and cannot edit or update
        $this->actingAs($author)->get(route('shift-comm.show', $oldComm))
            ->assertOk()
            ->assertDontSee('>Edit</a>', false);
        $this->actingAs($author)->get(route('shift-comm.edit', $oldComm))
            ->assertForbidden();
        $this->actingAs($author)->put(route('shift-comm.update', $oldComm), $this->payload(['problem' => 'Hacked change']))
            ->assertForbidden();

        // Administrator also cannot edit or update an expired record
        $this->actingAs($admin)->get(route('shift-comm.show', $oldComm))
            ->assertOk()
            ->assertDontSee('>Edit</a>', false);
        $this->actingAs($admin)->get(route('shift-comm.edit', $oldComm))
            ->assertForbidden();
        $this->actingAs($admin)->put(route('shift-comm.update', $oldComm), $this->payload(['problem' => 'Admin change']))
            ->assertForbidden();

        // 2. Record created 2 hours ago (within the 24-hour grace period)
        $this->actingAs($author)->post(route('shift-comm.store'), $this->payload(['problem' => 'Recent record']));
        $recentComm = ShiftComm::latest('id')->firstOrFail();
        $recentComm->created_at = now()->subHours(2);
        $recentComm->save();

        $this->actingAs($author)->get(route('shift-comm.show', $recentComm))
            ->assertOk()
            ->assertSee('>Edit</a>', false);
        $this->actingAs($author)->get(route('shift-comm.edit', $recentComm))
            ->assertOk();
        $this->actingAs($author)->put(route('shift-comm.update', $recentComm), $this->payload(['problem' => 'Updated recent record']))
            ->assertRedirect(route('shift-comm.show', $recentComm));
        $this->assertDatabaseHas('shift_comms', ['id' => $recentComm->id, 'problem' => 'Updated recent record']);

        // 3. Make grace period dynamic: increase to 48 hours in settings
        \App\Models\Setting::set('shift_comm_edit_grace_period_hours', '48');
        $this->assertSame(48, ShiftComm::editGracePeriodHours());

        // Now the 25-hour-old record becomes editable again
        $this->actingAs($author)->get(route('shift-comm.show', $oldComm))
            ->assertOk()
            ->assertSee('>Edit</a>', false);
        $this->actingAs($author)->get(route('shift-comm.edit', $oldComm))
            ->assertOk();
        $this->actingAs($author)->put(route('shift-comm.update', $oldComm), $this->payload(['problem' => 'Updated old record with extended window']))
            ->assertRedirect(route('shift-comm.show', $oldComm));
        $this->assertDatabaseHas('shift_comms', ['id' => $oldComm->id, 'problem' => 'Updated old record with extended window']);
    }
}
