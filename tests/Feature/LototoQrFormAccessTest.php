<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Http\Middleware\AllowLototoQrForm;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\LototoActivity;
use App\Models\LototoBoard;
use App\Models\SpareLock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LototoQrFormAccessTest extends TestCase
{
    use RefreshDatabase;

    private LototoBoard $board;
    private Employee $owner;
    private Employee $colleague;
    private SpareLock $spare;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $unit = BusinessUnit::factory()->create(['name' => 'Half Product']);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $unit->id, 'division_name' => 'Maintenance', 'code' => 'board-qr-test',
            'title' => 'MAINTENANCE · HALF PRODUCT', 'subtitle' => 'Test', 'layout' => ['board_columns' => 20],
            'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $this->owner = Employee::factory()->create(['business_unit_id' => $unit->id, 'lototo_number' => '07']);
        $this->colleague = Employee::factory()->create(['business_unit_id' => $unit->id, 'lototo_number' => '08']);
        $this->spare = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-01', 'is_active' => true]);
    }

    public function test_a_signed_personal_qr_opens_the_form_without_login_and_unsigned_or_altered_links_do_not(): void
    {
        $this->get($this->personalQr($this->owner))
            ->assertOk()
            ->assertSee('value="07"', false)
            ->assertSee('name="board_id" value="'.$this->board->id.'"', false);
        $this->assertGuest();

        // Unsigned, or with another lock's number put into a signed link: the old login wall (Guest account entry).
        $this->get(route('safety.lototo.form', ['lototo_number' => '07', 'employee_id' => $this->owner->id, 'board' => $this->board->id]))
            ->assertRedirect(route('guest-login'));
        $altered = str_replace('lototo_number=07', 'lototo_number=08', $this->personalQr($this->owner));
        $altered = str_replace('employee_id='.$this->owner->id, 'employee_id='.$this->colleague->id, $altered);
        $this->get($altered)->assertRedirect(route('guest-login'));
        $this->assertGuest();
    }

    public function test_a_qr_visitor_runs_in_outstanding_and_spare_out_for_the_qr_owner_only(): void
    {
        $page = $this->get($this->personalQr($this->owner))->assertOk()->getContent();
        $action = $this->formAction($page);
        $this->assertStringContainsString('signature=', $action);

        $redirect = $this->post($action, $this->answers('07', 'in'))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->headers->get('Location');
        $activity = LototoActivity::query()->where('employee_id', $this->owner->id)->sole();
        $this->assertSame('in_progress', $activity->status);
        $this->assertSame($this->board->code, $activity->board);
        // Back on the same signed QR form, still without a login.
        $this->get($redirect)->assertOk()->assertSee('Aktivitas LOTOTO berhasil dimulai.');

        // Outstanding: the personal lock is closed and the chosen spare takes over.
        $this->post($action, ['lototo_number' => '07', 'employee_id' => $this->owner->id, 'board_id' => $this->board->id,
            'lock_status' => 'out', 'outcome' => 'outstanding', 'spare_lock_id' => $this->spare->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('outstanding', $activity->fresh()->outcome);
        $spareActivityId = data_get($activity->fresh()->out_details, 'spare_activity_id');
        $this->assertNotSame($activity->id, $spareActivityId);
        $this->assertSame($spareActivityId, $this->spare->fresh()->current_activity_id);

        // The spare QR now identifies its holder, so the holder closes it without a login.
        $sparePage = $this->get($this->spareQr())->assertOk()->assertSee('GEMBOK SPARE SP-01')->assertSee('value="07"', false)->getContent();
        $redirect = $this->post($this->formAction($sparePage), ['lototo_number' => '07', 'board_id' => $this->board->id,
            'lock_mode' => 'spare', 'lock_status' => 'out', 'outcome' => 'finish', 'spare_lock_id' => $this->spare->id])
            ->assertSessionHasNoErrors()->assertRedirect()->headers->get('Location');
        $this->assertNull($this->spare->fresh()->current_activity_id);
        $this->assertNotNull(data_get(LototoActivity::query()->find($spareActivityId)->out_details, 'spare_ended_at'));
        $this->assertNull(data_get($activity->fresh()->out_details, 'spare_ended_at'));

        // The free spare's QR names nobody, so the holder lands on their own personal QR form, not on a 403.
        $this->assertSame($this->personalQr($this->owner), $redirect);
        $this->get($redirect)->assertOk()->assertSee('Aktivitas gembok spare selesai dan gembok tersedia kembali.');
        // The now free spare's QR asks for a login (Spare IN is for Safety administrators) and shows nothing else.
        $this->get($this->spareQr())->assertRedirect(route('guest-login'));
        $this->assertGuest();
    }

    public function test_a_qr_visitor_cannot_act_for_another_employee_board_or_take_a_free_spare(): void
    {
        $action = $this->formAction($this->get($this->personalQr($this->owner))->getContent());

        // Another lock owner in the posted fields: refused, the signed QR decides who acts.
        $this->post($action, [...$this->answers('08', 'in'), 'employee_id' => $this->colleague->id])->assertForbidden();
        // Another board in the posted fields.
        $other = LototoBoard::query()->create([...$this->board->only(['business_unit_id', 'title', 'subtitle', 'layout', 'component_settings', 'is_active']),
            'division_name' => 'Other', 'code' => 'board-qr-other']);
        $this->post($action, [...$this->answers('07', 'in'), 'board_id' => $other->id])->assertForbidden();
        // A free spare: Spare IN stays with Safety administrators. Its QR asks for a login instead of opening the form,
        // and an IN posted with the QR's signature is still refused.
        $this->get($this->spareQr())->assertRedirect(route('guest-login'));
        $this->post(AllowLototoQrForm::url('safety.lototo.form.store', ['spare_lock' => 'SP-01', 'board' => $this->board->id]), [
            ...$this->answers('07', 'in'), 'lock_mode' => 'spare', 'spare_lock_id' => $this->spare->id,
        ])->assertForbidden();
        // The signature only covers the form, never other LOTOTO pages.
        $this->get(route('safety.lototo.boards.show', $this->board))->assertRedirect(route('guest-login'));
        $this->get(route('safety.lototo.spare-locks.show', $this->spare))->assertRedirect(route('guest-login'));

        $this->assertDatabaseCount('lototo_activities', 0);
        $this->assertNull($this->spare->fresh()->current_activity_id);
    }

    public function test_the_shared_guest_account_is_treated_as_a_qr_visitor_and_logged_in_users_keep_their_rules(): void
    {
        $guest = User::factory()->create();
        $guest->assignRole(RoleName::Guest->value);
        $this->actingAs($guest)->get($this->personalQr($this->owner))->assertOk();
        $this->actingAs($guest)->get(route('safety.lototo.form', ['lototo_number' => '07', 'board' => $this->board->id]))->assertForbidden();

        // A logged-in employee still acts only as themselves, even on a colleague's signed QR.
        $user = User::factory()->create();
        $user->assignRole(RoleName::MaintenanceStaff->value);
        $this->colleague->update(['user_id' => $user->id]);
        $this->actingAs($user)->get(route('safety.lototo.form', ['lototo_number' => '08', 'board' => $this->board->id]))->assertOk();
        $this->actingAs($user)->get($this->personalQr($this->owner))->assertForbidden();
        $this->actingAs($user)->post(route('safety.lototo.form.store'), [...$this->answers('08', 'in'), 'employee_id' => $this->colleague->id])
            ->assertRedirect(route('safety.lototo.form', ['lototo_number' => '08', 'employee_id' => $this->colleague->id, 'board' => $this->board->id]));
        $this->assertSame(1, LototoActivity::query()->where('employee_id', $this->colleague->id)->count());

        // Without ViewSafety the form stays closed to an account, signature or not.
        $noSafety = User::factory()->create();
        $this->actingAs($noSafety)->get($this->personalQr($this->owner))->assertForbidden();

        // An administrator keeps full access, including Spare IN.
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $this->actingAs($admin)->get(route('safety.lototo.form', ['spare_lock' => 'SP-01', 'board' => $this->board->id]))->assertOk();
    }

    public function test_the_qr_code_page_prints_signed_links_that_open_the_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $page = $this->actingAs($admin)->get(route('safety.lototo.qr-codes', ['board' => $this->board->id]))->assertOk()->getContent();

        preg_match('/data-qrcode="([^"]*spare_lock=SP-01[^"]*)"/', $page, $spare);
        $this->assertNotEmpty($spare);
        $this->assertStringContainsString('signature=', $spare[1]);

        auth()->logout();
        $this->owner->update(['user_id' => null]);
        $lock = $this->spare;
        $activity = LototoActivity::query()->create(['employee_id' => $this->owner->id, 'spare_lock_id' => $lock->id, 'board' => $this->board->code,
            'location' => 'Pump', 'activity' => 'Valve', 'risks' => ['terjepit'], 'risk_controls' => 'Lock', 'knows_procedure' => true,
            'follows_lototo' => true, 'uses_ppe' => true, 'safe_tools' => true, 'safe_hands' => true, 'status' => 'in_progress',
            'out_details' => ['spare_only' => true], 'started_at' => now()]);
        $lock->update(['current_activity_id' => $activity->id]);
        $this->get(html_entity_decode($spare[1]))->assertOk()->assertSee('GEMBOK SPARE SP-01')->assertSee('value="07"', false);
    }

    private function personalQr(Employee $employee): string
    {
        return AllowLototoQrForm::url('safety.lototo.form', ['lototo_number' => $employee->lototo_number, 'employee_id' => $employee->id, 'board' => $this->board->id]);
    }

    private function spareQr(): string
    {
        return AllowLototoQrForm::url('safety.lototo.form', ['spare_lock' => 'SP-01', 'board' => $this->board->id]);
    }

    private function formAction(string $page): string
    {
        preg_match('/<form method="POST" action="([^"]+)"/', $page, $match);

        return html_entity_decode($match[1]);
    }

    private function answers(string $lototoNumber, string $status): array
    {
        return [
            'lototo_number' => $lototoNumber, 'employee_id' => $this->owner->id, 'board_id' => $this->board->id, 'lock_status' => $status,
            'location' => 'Mesin 3', 'activity' => 'Ganti bearing', 'risks' => ['kesetrum'], 'risk_controls' => 'Isolasi energi',
            'knows_procedure' => 'ya', 'follows_lototo' => 'ya', 'uses_ppe' => 'ya', 'safe_tools' => 'ya', 'safe_hands' => 'ya',
        ];
    }
}
