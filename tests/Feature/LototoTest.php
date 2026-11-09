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
use App\Notifications\LototoSafetyAlert;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpareLockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LototoTest extends TestCase
{
    use RefreshDatabase;

    private EmploymentStatus $activeStatus;
    private User $lototoManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SpareLockSeeder::class);
        $this->activeStatus = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->lototoManager = User::factory()->create();
        $this->lototoManager->assignRole(RoleName::Administrator->value);
    }

    public function test_board_only_shows_unit_one_employees_and_renders_other_and_spare_slots(): void
    {
        $unitOne = $this->legacyBusinessUnit();
        $otherUnit = BusinessUnit::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);

        $technician = Employee::factory()->create([
            'business_unit_id' => $unitOne->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '01',
        ]);
        $supervisor = Employee::factory()->create([
            'business_unit_id' => $unitOne->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '101',
        ]);
        $this->assignEmployeeToBoardSlot($technician, 'technician');
        $this->assignEmployeeToBoardSlot($supervisor, 'supervisor');
        Employee::factory()->create([
            'business_unit_id' => $otherUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '02',
        ]);
        Employee::factory()->create([
            'business_unit_id' => $otherUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '102',
        ]);

        $response = $this->actingAs($user)->get(route('safety.lototo.boards.show', LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail()));

        $response->assertOk();
        $response->assertViewHas('rows', fn ($rows) => $rows['TECHNICIAN']->count() === 1
            && $rows['SUPERVISOR']->count() === 1);
        $response->assertSee('TECHNICIAN');
        $response->assertSee('lototo-lock-card', false);
        $response->assertSee('DANGER');
        $response->assertSee('OTHER');
        $response->assertSee('aria-label="Gembok spare SP-20"', false);
        $response->assertSee(route('safety.lototo.spare-locks.show', SpareLock::query()->where('code', 'SP-01')->firstOrFail()), false);
        $spareLabels = [];
        preg_match_all('/aria-label="Gembok spare ([^"]+)"/', $response->getContent(), $spareLabels);
        $visibleSpareLabels = array_filter($spareLabels[1], fn (string $code) => $code !== '__KODE__');
        $this->assertSame(20, count($visibleSpareLabels), implode(', ', $spareLabels[1]));
        $this->assertDatabaseCount('spare_locks', 20);
        $this->assertSame(15, substr_count($response->getContent(), '>Tool '));
    }

    public function test_board_keeps_slot_indexes_and_assignments_independent_from_employee_ids(): void
    {
        $businessUnit = $this->legacyBusinessUnit();
        $laterNumber = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '17',
        ]);
        $earlierNumber = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '03',
        ]);
        LototoBoardSlot::query()
            ->where('category', 'technician')
            ->where('position', 1)
            ->update(['employee_id' => $earlierNumber->id]);
        LototoBoardSlot::query()
            ->where('category', 'technician')
            ->where('position', 3)
            ->update(['employee_id' => $laterNumber->id]);
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($user)->get(route('safety.lototo.boards.show', LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail()));

        // Each slot shows the employee saved on it, whatever their LOTOTO number.
        $response->assertOk()
            ->assertSeeInOrder(['LOTOTO 03', '>01</span>', 'TECHNICIAN posisi 2 kosong', 'LOTOTO 17', '>03</span>'], false)
            ->assertDontSee('TECHNICIAN posisi 1 kosong', false)
            ->assertDontSee('TECHNICIAN posisi 3 kosong', false)
            ->assertSee('TECHNICIAN posisi 17 kosong', false)
            ->assertSee('TECHNICIAN posisi 64 kosong', false)
            ->assertSee('TECHNICIAN posisi 65 kosong', false)
            ->assertDontSee('TECHNICIAN posisi 66 kosong', false);

        $content = $response->getContent();
        $technicianStart = strpos($content, 'class="lototo-grid lototo-technician-grid"');
        $this->assertNotFalse($technicianStart);
        $technicianEnd = strpos($content, '</section>', $technicianStart);
        // The 65 Technician slots stored for the board, all drawn even though the highest LOTOTO number is 17.
        $this->assertSame(
            65,
            substr_count(substr($content, $technicianStart, $technicianEnd - $technicianStart), 'lototo-position-index'),
        );
    }

    public function test_employees_outside_unit_one_cannot_open_a_lototo_activity_page(): void
    {
        $this->legacyBusinessUnit();
        $otherUnit = BusinessUnit::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);
        $employee = Employee::factory()->create([
            'business_unit_id' => $otherUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '101',
        ]);

        $response = $this->actingAs($user)->get(route('safety.lototo.show', $employee));

        $response->assertNotFound();
    }

    public function test_board_replaces_employee_locks_with_active_employee_details_until_out(): void
    {
        $this->actingAs($this->lototoManager);
        $businessUnit = $this->legacyBusinessUnit();
        $technician = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '07',
        ]);
        $supervisor = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '101',
        ]);
        $this->assignEmployeeToBoardSlot($technician, 'technician');
        $this->assignEmployeeToBoardSlot($supervisor, 'supervisor');
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'in'))
            ->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));
        $this->post(route('safety.lototo.form.store'), $this->activityFormData('101', 'in'))
            ->assertRedirect(route('safety.lototo.form', ['lototo_number' => '101']));

        $board = $this->actingAs($user)
            ->get(route('safety.lototo.boards.show', LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail()))
            ->assertOk()
            ->getContent();

        foreach ([$technician, $supervisor] as $employee) {
            $cardUrl = route('safety.lototo.show', ['employee' => $employee, 'board' => LototoBoard::query()->where('code', 'mtn_tr')->value('id')]);
            preg_match(
                '/<a[^>]*aria-label="'.preg_quote($employee->full_name.' LOTOTO '.$employee->lototo_number.', status IN', '/').'".*?<\/a>/s',
                $board,
                $employeeCard,
            );
            $this->assertNotEmpty($employeeCard);
            $this->assertStringContainsString('href="'.$cardUrl.'"', $employeeCard[0]);
            $this->assertStringContainsString('lototo-active-card', $employeeCard[0]);
            $this->assertStringContainsString('status IN', $employeeCard[0]);
            $this->assertStringContainsString('>IN</span>', $employeeCard[0]);
            $this->assertStringNotContainsString('lototo-lock-shackle', $employeeCard[0]);
        }

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'out', [
            'outcome' => 'finish',
        ]))->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));
        $this->post(route('safety.lototo.form.store'), $this->activityFormData('101', 'out', [
            'outcome' => 'finish',
        ]))->assertRedirect(route('safety.lototo.form', ['lototo_number' => '101']));

        $board = $this->get(route('safety.lototo.boards.show', LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail()))
            ->assertOk()
            ->getContent();

        foreach ([$technician, $supervisor] as $employee) {
            $cardUrl = route('safety.lototo.show', ['employee' => $employee, 'board' => LototoBoard::query()->where('code', 'mtn_tr')->value('id')]);
            preg_match(
                '/<a[^>]*aria-label="'.preg_quote($employee->full_name.' LOTOTO '.$employee->lototo_number.', status OUT', '/').'".*?<\/a>/s',
                $board,
                $employeeCard,
            );
            $this->assertNotEmpty($employeeCard);
            $this->assertStringContainsString('href="'.$cardUrl.'"', $employeeCard[0]);
            $this->assertStringContainsString('lototo-lock-card', $employeeCard[0]);
            $this->assertStringNotContainsString('lototo-active-card', $employeeCard[0]);
        }
    }

    public function test_activity_form_is_separate_and_accepts_a_lototo_number_from_the_qr_url(): void
    {
        $this->legacyBusinessUnit();
        $this->actingAs($this->lototoManager);
        SpareLock::query()->where('code', 'SP-02')->update(['is_active' => false]);

        $response = $this->get(route('safety.lototo.form', [
            'lototo_number' => '07',
        ]));

        $response->assertOk();
        $response->assertSee('value="07"', false);
        $response->assertSee('MAINTENANCE · TIRE CORD');
        $response->assertSee('name="lototo_board" value="mtn_tr"', false);
        $response->assertDontSee('Pilih board');
        $response->assertSee('Jari / tangan saya AMAN');
        $response->assertSee('Close / Finish');
        $response->assertSee('Outstanding / Handover');
        $response->assertSee('Pilih gembok spare untuk handover');
        $response->assertSee('SP-01');
        $response->assertDontSee('>SP-02</option>', false);
        $response->assertSee('Pastikan semua jawaban sudah benar sebelum mengirim.');
        $response->assertSee('type="submit"', false);
    }

    public function test_lototo_form_requires_safety_permission_and_spare_qr_accepts_manage_safety_permission(): void
    {
        $this->legacyBusinessUnit();
        auth()->logout();
        $this->get(route('safety.lototo.form', ['spare_lock' => 'SP-02']))
            ->assertRedirect(route('guest-login'));

        $withoutSafetyPermission = User::factory()->create();
        $this->actingAs($withoutSafetyPermission)
            ->get(route('safety.lototo.form', ['spare_lock' => 'SP-02']))
            ->assertForbidden();

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $this->actingAs($manager)
            ->get(route('safety.lototo.form', ['spare_lock' => 'SP-02']))
            ->assertOk()
            ->assertSee('GEMBOK SPARE SP-02')
            ->assertSee('value="mtn_tr"', false);
    }

    public function test_manage_safety_users_can_open_the_qr_code_page_and_get_qr_for_each_lock_type(): void
    {
        $businessUnit = $this->legacyBusinessUnit();
        $technician = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '07',
        ]);
        $supervisor = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '101',
        ]);
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $board = LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail();

        $qr = fn (array $parameters) => e(\App\Http\Middleware\AllowLototoQrForm::url('safety.lototo.form', [...$parameters, 'board' => $board->id]));
        $this->actingAs($manager)
            ->get(route('safety.lototo.qr-codes'))
            ->assertOk()
            ->assertSee('Manage Board')
            ->assertDontSee('Kelola Gembok Spare')
            ->assertSee('data-qrcode="'.$qr(['lototo_number' => '07', 'employee_id' => $technician->id]).'"', false)
            ->assertSee('data-qrcode="'.$qr(['lototo_number' => '101', 'employee_id' => $supervisor->id]).'"', false)
            ->assertSee('data-qrcode="'.$qr(['spare_lock' => 'SP-02']).'"', false)
            ->assertSee('data-print-open="technician-'.$technician->id.'"', false)
            ->assertSee('data-print-open="spare-'.SpareLock::query()->where('code', 'SP-02')->value('id').'"', false);
    }

    public function test_out_submission_reuses_in_answers_and_does_not_require_reentering_the_checklist(): void
    {
        $this->actingAs($this->lototoManager);
        $businessUnit = $this->legacyBusinessUnit();
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '07',
        ]);
        $this->assignEmployeeToBoardSlot($employee, 'technician');
        $spareLock = SpareLock::query()->where('code', 'SP-01')->firstOrFail();

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'in', [
            'location' => 'Pump room',
            'activity' => 'Replace valve',
            'risks' => ['kesetrum', 'terjepit'],
        ]))->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));

        $activity = $employee->lototoActivities()->latest('started_at')->firstOrFail();
        $this->assertSame('mtn_tr', $activity->board);

        $this->get(route('safety.lototo.form', ['lototo_number' => '07']))
            ->assertOk()
            ->assertSee('Data pemeriksaan IN tersimpan')
            ->assertSee('Replace valve')
            ->assertDontSee('Kenali dan kendalikan risiko');

        $this->post(route('safety.lototo.form.store'), [
            'lototo_number' => '07',
            'lock_mode' => 'personal',
            'lock_status' => 'out',
            'outcome' => 'outstanding',
            'spare_lock_id' => $spareLock->id,
        ])->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));

        $activity->refresh();
        $this->assertSame('completed', $activity->status);
        $this->assertSame('outstanding', $activity->outcome);
        $this->assertSame('Pump room', $activity->location);
        $this->assertSame('Replace valve', $activity->activity);
        $this->assertSame(['kesetrum', 'terjepit'], $activity->risks);
        // The personal record stays personal; the spare continues on its own record.
        $this->assertNull($activity->spare_lock_id);
        $this->assertSame('SP-01', data_get($activity->out_details, 'spare_lock_code'));
        $this->assertNotNull($activity->ended_at);

        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);
        $this->actingAs($user)
            ->get(route('safety.lototo.show', $employee))
            ->assertOk()
            ->assertDontSee('Belum ada aktivitas LOTOTO untuk karyawan ini')
            ->assertSee('Pump room')
            ->assertSee('CLOSE');

        $this->assertSame(2, $employee->lototoActivities()->count());
        $spareActivity = LototoActivity::query()->whereKey(data_get($activity->out_details, 'spare_activity_id'))->firstOrFail();
        $this->assertNotSame($activity->id, $spareActivity->id);
        $this->assertSame($spareLock->id, $spareActivity->spare_lock_id);
        $this->assertSame($activity->id, data_get($spareActivity->out_details, 'handover_from_activity_id'));
        $this->assertSame('Pump room', $spareActivity->location);
        $this->assertSame($spareActivity->id, $spareLock->fresh()->current_activity_id);
        $this->assertNotNull(data_get($spareActivity->out_details, 'spare_started_at'));

        $this->get(route('safety.lototo.form', ['spare_lock' => 'SP-01']))
            ->assertOk()
            ->assertSee('GEMBOK SPARE SP-01')
            ->assertSee('OUT — aktivitas mulai')
            ->assertSee('Close / Finish (OUT)')
            ->assertDontSee('Kenali dan kendalikan risiko');

        $this->post(route('safety.lototo.form.store'), [
            'lototo_number' => '07',
            'lock_mode' => 'spare',
            'lock_status' => 'out',
            'outcome' => 'finish',
            'spare_lock_id' => $spareLock->id,
        ])->assertRedirect(route('safety.lototo.form', ['spare_lock' => 'SP-01']));

        $this->assertNotNull(data_get($spareActivity->fresh()->out_details, 'spare_ended_at'));
        $this->assertSame('completed', $spareActivity->fresh()->status);
        $this->assertSame(2, $employee->lototoActivities()->count());
        $this->assertSame('completed', $activity->fresh()->status);
        $this->assertSame('outstanding', $activity->fresh()->outcome);
        $this->assertNull($spareLock->fresh()->current_activity_id);
    }

    public function test_spare_lock_activity_page_is_scoped_to_the_selected_lock(): void
    {
        $this->legacyBusinessUnit();
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);
        $spareLock = SpareLock::query()->where('code', 'SP-07')->firstOrFail();

        $this->actingAs($user)
            ->get(route('safety.lototo.spare-locks.show', $spareLock))
            ->assertOk()
            ->assertSee('SP-07')
            ->assertSee('Form IN / Mulai')
            ->assertSee('Belum ada aktivitas yang tercatat untuk gembok spare SP-07');
    }

    public function test_outstanding_marks_spare_in_use_and_finish_releases_it(): void
    {
        $this->actingAs($this->lototoManager);
        $businessUnit = $this->legacyBusinessUnit();
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '07',
        ]);
        $this->assignEmployeeToBoardSlot($employee, 'technician');
        $spareLock = SpareLock::query()->where('code', 'SP-01')->firstOrFail();

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'in'))
            ->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));

        $activity = $employee->lototoActivities()->latest('started_at')->firstOrFail();
        $this->assertSame('in_progress', $activity->status);

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'out', [
            'outcome' => 'outstanding',
            'spare_lock_id' => $spareLock->id,
        ]))->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));

        $personalActivity = $activity->fresh();
        $this->assertSame('completed', $personalActivity->status);
        $this->assertSame('outstanding', $personalActivity->outcome);
        $this->assertNull($personalActivity->spare_lock_id);
        $spareActivity = LototoActivity::query()->where('spare_lock_id', $spareLock->id)->sole();
        $this->assertNotSame($personalActivity->id, $spareActivity->id);
        $this->assertSame($spareActivity->id, $spareLock->fresh()->current_activity_id);
        $this->assertNotNull(data_get($spareActivity->out_details, 'spare_started_at'));

        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);
        $board = $this->actingAs($user)
            ->followingRedirects()
            ->get(route('safety.lototo.boards.show', LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail()))
            ->assertOk()
            ->getContent();
        preg_match('/<a[^>]*aria-label="Gembok spare SP-01".*?<\/a>/s', $board, $spareCard);
        $this->assertNotEmpty($spareCard);
        $this->assertStringContainsString('lototo-active-card', $spareCard[0]);
        $this->assertStringNotContainsString('lototo-lock-shackle', $spareCard[0]);
        $this->assertStringContainsString($employee->full_name, $spareCard[0]);
        $this->assertStringContainsString('>07</span>', $spareCard[0]);
        $this->assertStringContainsString('>IN</span>', $spareCard[0]);
        preg_match(
            '/<a[^>]*aria-label="'.preg_quote($employee->full_name.' LOTOTO 07', '/').'.*?<\/a>/s',
            $board,
            $personalCard,
        );
        $this->assertNotEmpty($personalCard);
        $this->assertStringContainsString('lototo-lock-card', $personalCard[0]);
        $this->assertStringNotContainsString('lototo-active-card', $personalCard[0]);

        $this->actingAs($user)
            ->get(route('safety.lototo.spare-locks.show', $spareLock))
            ->assertOk()
            ->assertSee($employee->full_name)
            ->assertSee('IN')
            ->assertSee('TEKNISI')
            ->assertSee('Form OUT / CLOSE');

        $this->post(route('safety.lototo.form.store'), [
            'lototo_number' => '07',
            'lock_mode' => 'spare',
            'lock_status' => 'out',
            'outcome' => 'finish',
            'spare_lock_id' => $spareLock->id,
        ])->assertRedirect(route('safety.lototo.form', ['spare_lock' => 'SP-01']));

        $this->assertNotNull(data_get($spareActivity->fresh()->out_details, 'spare_ended_at'));
        $this->assertSame(2, $employee->lototoActivities()->count());
        $this->assertSame('completed', $activity->fresh()->status);
        $this->assertNull($spareLock->fresh()->current_activity_id);

        $board = $this->get(route('safety.lototo.boards.show', LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail()))
            ->assertOk()
            ->getContent();
        preg_match('/<a[^>]*aria-label="Gembok spare SP-01".*?<\/a>/s', $board, $spareCard);
        $this->assertNotEmpty($spareCard);
        $this->assertStringContainsString('lototo-lock-card', $spareCard[0]);
        $this->assertStringContainsString('lototo-lock-card--spare', $spareCard[0]);
        $this->assertStringContainsString('lototo-lock-shackle', $spareCard[0]);
        $this->assertStringContainsString('>SP-01</span>', $spareCard[0]);
    }

    public function test_spare_qr_can_start_a_standalone_activity_without_handover(): void
    {
        $businessUnit = $this->legacyBusinessUnit();
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '07',
        ]);
        $spareLock = SpareLock::query()->where('code', 'SP-01')->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);

        $this->actingAs($user)->get(route('safety.lototo.form', ['spare_lock' => 'SP-01']))
            ->assertOk()
            ->assertSee('Mulai IN gembok spare')
            ->assertSee('Kenali dan kendalikan risiko');

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'in', [
            'lock_mode' => 'spare',
            'spare_lock_id' => $spareLock->id,
            'location' => 'Spare-only area',
        ]))->assertRedirect(route('safety.lototo.form', ['spare_lock' => 'SP-01']));

        $activity = $employee->lototoActivities()->sole();
        $this->assertSame('in_progress', $activity->status);
        $this->assertSame($spareLock->id, $activity->spare_lock_id);
        $this->assertSame($activity->id, $spareLock->fresh()->current_activity_id);
        $this->assertTrue(data_get($activity->out_details, 'spare_only'));

        $this->post(route('safety.lototo.form.store'), [
            'lototo_number' => '07',
            'lock_mode' => 'spare',
            'lock_status' => 'out',
            'outcome' => 'finish',
            'spare_lock_id' => $spareLock->id,
        ])->assertRedirect(route('safety.lototo.form', ['spare_lock' => 'SP-01']));

        $activity->refresh();
        $this->assertSame('completed', $activity->status);
        $this->assertSame('finish', $activity->outcome);
        $this->assertNotNull($activity->ended_at);
        $this->assertNotNull(data_get($activity->out_details, 'spare_ended_at'));
        $this->assertNull($spareLock->fresh()->current_activity_id);
        $this->assertSame(1, $employee->lototoActivities()->count());
    }

    public function test_only_the_spare_user_or_an_administrator_can_close_a_spare_lock(): void
    {
        $businessUnit = $this->legacyBusinessUnit();
        $owner = User::factory()->create();
        $owner->assignRole(RoleName::MaintenanceStaff->value);
        $ownerEmployee = Employee::factory()->forUser($owner)->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '07',
        ]);
        $otherUser = User::factory()->create();
        $otherUser->assignRole(RoleName::MaintenanceStaff->value);
        Employee::factory()->forUser($otherUser)->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '08',
        ]);
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceStaff->value);
        $supervisorEmployee = Employee::factory()->forUser($supervisorUser)->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '101',
        ]);
        $spareLock = SpareLock::query()->where('code', 'SP-01')->firstOrFail();
        $supervisorSpareLock = SpareLock::query()->where('code', 'SP-02')->firstOrFail();
        $activity = LototoActivity::query()->create([
            'employee_id' => $ownerEmployee->id,
            'spare_lock_id' => $spareLock->id,
            'board' => 'mtn_tr',
            'location' => 'Pump room',
            'activity' => 'Replace valve',
            'risks' => ['terjepit'],
            'risk_controls' => 'Lock and tag',
            'knows_procedure' => true,
            'follows_lototo' => true,
            'uses_ppe' => true,
            'safe_tools' => true,
            'safe_hands' => true,
            'status' => 'in_progress',
            'out_details' => ['spare_only' => true],
            'started_at' => now(),
        ]);
        $spareLock->update(['current_activity_id' => $activity->id]);
        $supervisorActivity = LototoActivity::query()->create([
            'employee_id' => $supervisorEmployee->id,
            'spare_lock_id' => $supervisorSpareLock->id,
            'board' => 'mtn_tr',
            'location' => 'Pump room',
            'activity' => 'Supervisor activity',
            'risks' => ['terjepit'],
            'risk_controls' => 'Lock and tag',
            'knows_procedure' => true,
            'follows_lototo' => true,
            'uses_ppe' => true,
            'safe_tools' => true,
            'safe_hands' => true,
            'status' => 'in_progress',
            'out_details' => ['spare_only' => true],
            'started_at' => now(),
        ]);
        $supervisorSpareLock->update(['current_activity_id' => $supervisorActivity->id]);

        $this->actingAs($owner)
            ->get(route('safety.lototo.form', ['spare_lock' => $spareLock->code]))
            ->assertOk()
            ->assertSee('OUT — aktivitas mulai');
        $this->get(route('safety.lototo.spare-locks.show', $spareLock))
            ->assertOk()
            ->assertSee('Form OUT / CLOSE');

        $this->actingAs($otherUser)
            ->get(route('safety.lototo.form', ['spare_lock' => $spareLock->code]))
            ->assertForbidden();
        $this->post(route('safety.lototo.form.store'), [
            'lototo_number' => $ownerEmployee->lototo_number,
            'lock_mode' => 'spare',
            'lock_status' => 'out',
            'outcome' => 'finish',
            'spare_lock_id' => $spareLock->id,
        ])->assertForbidden();

        $this->actingAs($supervisorUser)
            ->get(route('safety.lototo.form', ['spare_lock' => $supervisorSpareLock->code]))
            ->assertForbidden();
        $this->post(route('safety.lototo.form.store'), [
            'lototo_number' => $supervisorEmployee->lototo_number,
            'lock_mode' => 'spare',
            'lock_status' => 'out',
            'outcome' => 'finish',
            'spare_lock_id' => $supervisorSpareLock->id,
        ])->assertForbidden();

        $this->actingAs($owner)
            ->post(route('safety.lototo.form.store'), [
                'lototo_number' => $ownerEmployee->lototo_number,
                'lock_mode' => 'spare',
                'lock_status' => 'out',
                'outcome' => 'finish',
                'spare_lock_id' => $spareLock->id,
            ])
            ->assertRedirect(route('safety.lototo.form', ['spare_lock' => $spareLock->code]));

        $this->assertNull($spareLock->fresh()->current_activity_id);
        $this->assertSame($ownerEmployee->id, $activity->fresh()->employee_id);
        $this->assertSame($spareLock->id, $activity->fresh()->spare_lock_id);
        $this->assertSame('completed', $activity->fresh()->status);
        $this->assertNotNull(data_get($activity->fresh()->out_details, 'spare_ended_at'));
    }

    public function test_personal_and_multiple_spare_locks_can_be_active_for_the_same_employee(): void
    {
        $this->actingAs($this->lototoManager);
        $businessUnit = $this->legacyBusinessUnit();
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '07',
        ]);
        $otherEmployee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '08',
        ]);
        $spareLocks = SpareLock::query()->whereIn('code', ['SP-01', 'SP-02'])->orderBy('code')->get();
        $firstLock = $spareLocks->firstOrFail();
        $secondLock = $spareLocks->last();

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'in'))
            ->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));

        foreach ([$firstLock, $secondLock] as $spareLock) {
            $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'in', [
                'lock_mode' => 'spare',
                'spare_lock_id' => $spareLock->id,
            ]))->assertRedirect(route('safety.lototo.form', ['spare_lock' => $spareLock->code]));

            $this->assertNotNull($spareLock->fresh()->current_activity_id);
        }

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'out', [
            'outcome' => 'finish',
        ]))->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));

        $personalActivity = $employee->lototoActivities()
            ->whereNull('spare_lock_id')
            ->latest('started_at')
            ->firstOrFail();
        $this->assertSame('completed', $personalActivity->status);
        $this->assertNotNull($firstLock->fresh()->current_activity_id);
        $this->assertNotNull($secondLock->fresh()->current_activity_id);

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'in'))
            ->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));
        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'out', [
            'outcome' => 'finish',
        ]))->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));

        $this->from(route('safety.lototo.form', ['spare_lock' => $firstLock->code]))
            ->post(route('safety.lototo.form.store'), [
                'lototo_number' => $otherEmployee->lototo_number,
                'lock_mode' => 'spare',
                'lock_status' => 'out',
                'outcome' => 'finish',
                'spare_lock_id' => $firstLock->id,
            ])
            ->assertSessionHasErrors('spare_lock_id');
        $this->assertNotNull($firstLock->fresh()->current_activity_id);

        foreach ([$firstLock, $secondLock] as $spareLock) {
            $this->post(route('safety.lototo.form.store'), [
                'lototo_number' => $employee->lototo_number,
                'lock_mode' => 'spare',
                'lock_status' => 'out',
                'outcome' => 'finish',
                'spare_lock_id' => $spareLock->id,
            ])->assertRedirect(route('safety.lototo.form', ['spare_lock' => $spareLock->code]));

            $this->assertNull($spareLock->fresh()->current_activity_id);
        }
    }

    public function test_safety_alert_opens_supervisor_follow_up_and_saves_the_response(): void
    {
        $this->actingAs($this->lototoManager);
        Notification::fake();

        $businessUnit = $this->legacyBusinessUnit();
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisor = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '101',
            'user_id' => $supervisorUser->id,
        ]);
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '07',
            'supervisor_id' => $supervisor->id,
        ]);
        $this->assignEmployeeToBoardSlot($employee, 'technician');

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'in', [
            'supervisor_feedback' => 'Susah sinyal di area mesin.',
        ]))->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));

        $activity = $employee->lototoActivities()->sole();
        $this->assertSame('pending', $activity->supervisor_follow_up_status);
        Notification::assertSentTo(
            $supervisorUser,
            LototoSafetyAlert::class,
            fn (LototoSafetyAlert $notification): bool => $notification->toArray($supervisorUser)['url']
                === route('safety.lototo.follow-up', $activity)
                && str_contains($notification->toArray($supervisorUser)['message'], 'Susah sinyal di area mesin.')
        );

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $this->actingAs($admin)
            ->get(route('safety.lototo.boards.show', LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail()))
            ->assertOk()
            ->assertSee('LOTOTO follow-up required');

        $this->actingAs($supervisorUser)
            ->get(route('safety.lototo.follow-up', $activity))
            ->assertOk()
            ->assertSee('Susah sinyal di area mesin.')
            ->assertSee('Feedback / tindakan supervisor')
            ->assertSee('YA');

        $otherUser = User::factory()->create();
        $otherUser->assignRole(RoleName::MaintenanceSupervisor->value);
        Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'user_id' => $otherUser->id,
        ]);
        $this->actingAs($otherUser)
            ->get(route('safety.lototo.follow-up', $activity))
            ->assertForbidden();

        $this->actingAs($supervisorUser)
            ->post(route('safety.lototo.follow-up.store', $activity), [
                'supervisor_follow_up' => 'Saya akan mengatur bantuan untuk pemeriksaan area.',
            ])
            ->assertRedirect(route('safety.lototo.follow-up', $activity));

        $activity->refresh();
        $this->assertSame('completed', $activity->supervisor_follow_up_status);
        $this->assertSame('Saya akan mengatur bantuan untuk pemeriksaan area.', $activity->supervisor_follow_up);
        $this->assertSame($supervisorUser->id, $activity->supervisor_follow_up_by);
        $this->assertNotNull($activity->supervisor_follow_up_at);

        $this->actingAs($supervisorUser)
            ->get(route('safety.lototo.follow-up', $activity))
            ->assertOk()
            ->assertSee('STATUS: CLOSED')
            ->assertSee('Saya akan mengatur bantuan untuk pemeriksaan area.');
    }

    public function test_unsafe_answers_notify_the_assigned_supervisor(): void
    {
        $this->actingAs($this->lototoManager);
        Notification::fake();

        $businessUnit = $this->legacyBusinessUnit();
        $supervisorUser = User::factory()->create();
        $supervisor = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '101',
            'user_id' => $supervisorUser->id,
        ]);
        $employee = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_status_id' => $this->activeStatus->id,
            'lototo_number' => '07',
            'supervisor_id' => $supervisor->id,
        ]);
        $this->assignEmployeeToBoardSlot($employee, 'technician');

        $this->post(route('safety.lototo.form.store'), $this->activityFormData('07', 'in', [
            'knows_procedure' => 'tidak',
        ]))->assertRedirect(route('safety.lototo.form', ['lototo_number' => '07']));

        Notification::assertSentTo($supervisorUser, LototoSafetyAlert::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function activityFormData(string $lototoNumber, string $lockStatus, array $overrides = []): array
    {
        return array_merge([
            'lototo_number' => $lototoNumber,
            'lock_status' => $lockStatus,
            'location' => 'Test machine',
            'activity' => 'Test maintenance work',
            'risks' => ['kesetrum'],
            'risk_controls' => 'Isolate and verify zero energy',
            'knows_procedure' => 'ya',
            'follows_lototo' => 'ya',
            'uses_ppe' => 'ya',
            'safe_tools' => 'ya',
            'safe_hands' => 'ya',
        ], $overrides);
    }

    private function assignEmployeeToBoardSlot(Employee $employee, string $category): void
    {
        LototoBoardSlot::query()
            ->where('board_id', LototoBoard::query()->where('code', 'mtn_tr')->value('id'))
            ->where('category', $category)
            ->where('position', 1)
            ->update(['employee_id' => $employee->id]);
    }

    private function legacyBusinessUnit(): BusinessUnit
    {
        $businessUnit = BusinessUnit::factory()->create(['id' => 1, 'name' => 'TIRE CORD', 'code' => 'BU-TC']);
        $board = LototoBoard::query()->create([
            'business_unit_id' => $businessUnit->id,
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

        return $businessUnit;
    }
}
