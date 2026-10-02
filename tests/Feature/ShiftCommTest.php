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
