<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LototoActivity;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\LototoSupervisorFeedback;
use App\Models\User;
use App\Notifications\LototoFollowUpReply;
use App\Support\LototoEmployeePhoto;
use App\Support\LototoFollowUpLink;
use App\Support\LototoFollowUpWhatsApp;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The supervisor's follow-up reaches the employee: one email to the employee's own address, the confirmation popup with
 * the WhatsApp hand-over, the status of the activity, and the employee photos on the Employees pages.
 */
class LototoFollowUpNotifyTest extends TestCase
{
    use RefreshDatabase;

    private LototoBoard $board;
    private User $admin;
    private Employee $supervisor;
    private Employee $technician;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('public');
        LototoEmployeePhoto::forget();
        $this->seed(RolesAndPermissionsSeeder::class);
        $unit = BusinessUnit::factory()->create(['name' => 'Tire Cord', 'is_active' => true]);
        $active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $unit->id, 'division_name' => 'Maintenance', 'code' => 'mtn_tr', 'title' => 'BOARD', 'subtitle' => 'x',
            'layout' => ['board_columns' => 20], 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $this->supervisor = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'lototo_number' => '101',
            'full_name' => 'Sari Supervisor', 'email' => 'sari.supervisor@company.test']);
        $this->technician = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'lototo_number' => '07',
            'full_name' => 'Budi Teknisi', 'email' => 'budi@company.test', 'phone' => '0813-2823-5720', 'supervisor_id' => $this->supervisor->id]);
        LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'technician', 'position' => 7, 'is_enabled' => true, 'employee_id' => $this->technician->id]);
    }

    private function pending(): LototoActivity
    {
        return LototoActivity::query()->create(['employee_id' => $this->technician->id, 'board' => 'mtn_tr', 'location' => 'Gearbox Line 4', 'activity' => 'Ganti bearing',
            'risks' => ['listrik'], 'risk_controls' => 'LOTO', 'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true, 'safe_tools' => false, 'safe_hands' => true,
            'supervisor_feedback' => 'Kunci retak', 'supervisor_follow_up_status' => 'pending', 'status' => 'active', 'started_at' => now('UTC')]);
    }

    private function link(LototoActivity $activity): string
    {
        return LototoFollowUpLink::url($activity, $this->supervisor);
    }

    public function test_phone_numbers_are_normalised_to_indonesian_international_format(): void
    {
        foreach (['081328235720', '81328235720', '6281328235720', '+62 813-2823-5720', '0813 2823 5720'] as $input) {
            $this->assertSame('6281328235720', LototoFollowUpWhatsApp::normalize($input), $input);
        }
        foreach ([null, '', '   ', 'abc', '123', '0212345678', '021'] as $input) {
            $this->assertNull(LototoFollowUpWhatsApp::normalize($input), (string) $input);
        }
    }

    public function test_the_whatsapp_message_carries_the_greeting_activity_and_the_supervisors_text_encoded(): void
    {
        $activity = $this->pending();
        $url = LototoFollowUpWhatsApp::url($this->technician, $activity, "Ganti kunci & cek ulang?\nSegera.");
        $this->assertStringStartsWith('https://wa.me/6281328235720?text=', $url);
        $text = rawurldecode(substr($url, strlen('https://wa.me/6281328235720?text=')));
        $this->assertStringContainsString('Halo Budi Teknisi,', $text);
        $this->assertStringContainsString('aktivitas LOTOTO 07 di Gearbox Line 4', $text);
        $this->assertStringContainsString("Ganti kunci & cek ulang?\nSegera.", $text);
        $this->assertStringContainsString('Mohon segera menindaklanjuti informasi tersebut.', $text);
        $this->assertStringNotContainsString(' ', substr($url, 0, strpos($url, '?text=') + 6).str_replace('%20', '', explode('?text=', $url)[1]), 'nothing unencoded in the query');
        $this->technician->phone = null;
        $this->assertNull(LototoFollowUpWhatsApp::url($this->technician, $activity, 'x'));
    }

    public function test_a_reply_is_stored_and_emailed_once_to_the_employee_not_the_supervisor(): void
    {
        $activity = $this->pending();
        $action = $this->link($activity);
        $this->post($action, ['supervisor_follow_up' => 'Ganti kunci torsi.'])->assertRedirect();

        Notification::assertSentOnDemandTimes(LototoFollowUpReply::class, 1);
        Notification::assertSentOnDemand(LototoFollowUpReply::class, function (LototoFollowUpReply $notification, array $channels, $notifiable) {
            $this->assertSame(['mail'], $channels);
            $this->assertSame(['budi@company.test'], array_values($notifiable->routes));
            $mail = $notification->toMail($notifiable);
            $html = (string) $mail->render();
            $this->assertStringContainsString('FOLLOW UP LOTOTO', $mail->subject);
            foreach (['Budi Teknisi', 'Sari Supervisor', 'Gearbox Line 4', 'Ganti bearing', 'Ganti kunci torsi.', '>07<' === '' ? '' : '07'] as $text) {
                $this->assertStringContainsString($text, $html);
            }
            $this->assertStringNotContainsString('sari.supervisor@company.test', $html);
            preg_match('/href="([^"]+)"/', $html, $m);
            $this->assertStringContainsString('/safety/lototo/'.$this->technician->id, html_entity_decode($m[1]));

            return true;
        });
        $this->assertSame('completed', $activity->fresh()->supervisor_follow_up_status);

        // Sent again (double click, resent form, page refresh): stored once and emailed once.
        $this->post($action, ['supervisor_follow_up' => 'Ganti kunci torsi.'])->assertRedirect();
        Notification::assertSentOnDemandTimes(LototoFollowUpReply::class, 1);
        $this->assertSame(1, LototoSupervisorFeedback::query()->count());
        // A genuinely new reply is a new email.
        $this->post($action, ['supervisor_follow_up' => 'Sudah dicek ulang.'])->assertRedirect();
        Notification::assertSentOnDemandTimes(LototoFollowUpReply::class, 2);
    }

    public function test_the_popup_offers_whatsapp_only_with_a_number_and_nanti_saja_closes_it(): void
    {
        $activity = $this->pending();
        $action = $this->link($activity);
        $back = $this->post($action, ['supervisor_follow_up' => 'Ganti kunci torsi.'])->assertRedirect()->headers->get('Location');
        $page = $this->get($back)->assertOk()->getContent();
        foreach (['Follow Up Berhasil Dikirim', 'Budi Teknisi', 'budi@company.test', 'Lanjutkan Follow Up melalui WhatsApp?', 'Kirim via WhatsApp', 'Nanti saja', 'data-follow-up-close', 'https://wa.me/6281328235720?text='] as $text) {
            $this->assertStringContainsString($text, $page);
        }
        // The popup is shown once: opening the page again (refresh) does not show it, nor send anything.
        $this->get($back)->assertOk()->assertDontSee('Follow Up Berhasil Dikirim');
        Notification::assertSentOnDemandTimes(LototoFollowUpReply::class, 1);

        // No number: the page says so and has no WhatsApp button.
        $this->technician->update(['phone' => null]);
        $back = $this->post($action, ['supervisor_follow_up' => 'Balasan kedua.'])->assertRedirect()->headers->get('Location');
        $page = $this->get($back)->assertOk()->getContent();
        $this->assertStringContainsString('Nomor WhatsApp karyawan tidak tersedia.', $page);
        $this->assertStringNotContainsString('wa.me', $page);
        $this->assertStringNotContainsString('Kirim via WhatsApp', $page);
    }

    public function test_a_failed_email_is_reported_as_failed_and_the_reply_is_kept(): void
    {
        $activity = $this->pending();
        $this->technician->update(['email' => null]);
        $back = $this->post($this->link($activity), ['supervisor_follow_up' => 'Ganti kunci torsi.'])->assertRedirect()->headers->get('Location');
        $page = $this->get($back)->assertOk()->getContent();
        $this->assertStringContainsString('Email Gagal Dikirim', $page);
        $this->assertStringContainsString('Karyawan belum memiliki alamat email.', $page);
        $this->assertStringNotContainsString('Follow Up Berhasil Dikirim', $page);
        Notification::assertNothingSent();
        $this->assertSame(1, LototoSupervisorFeedback::query()->count(), 'the reply and its history are kept');
        $this->assertSame('completed', $activity->fresh()->supervisor_follow_up_status);
    }

    public function test_a_link_that_does_not_open_says_why(): void
    {
        $activity = $this->pending();
        $url = $this->link($activity);
        $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $url))->assertForbidden()->assertSee('tidak lengkap atau rusak');
        $this->travel(31)->days();
        $this->get($url)->assertForbidden()->assertSee('kedaluwarsa');
        $this->travelBack();
        $intruder = User::factory()->create();
        $intruder->assignRole(RoleName::MaintenanceStaff->value);
        $this->actingAs($intruder)->get($url)->assertForbidden()->assertSee('akun lain');
    }

    public function test_the_employees_page_shows_the_photo_through_the_app_or_a_placeholder(): void
    {
        Storage::disk('public')->put('employee-photos/afif.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $withPhoto = Employee::factory()->create(['business_unit_id' => $this->technician->business_unit_id, 'employment_status_id' => $this->technician->employment_status_id, 'full_name' => 'Aaa Afif Suryana', 'photo_path' => 'employee-photos/afif.png']);
        $missingFile = Employee::factory()->create(['business_unit_id' => $this->technician->business_unit_id, 'employment_status_id' => $this->technician->employment_status_id, 'full_name' => 'Aab Tanpa File', 'photo_path' => 'employee-photos/hilang.png']);
        $html = $this->actingAs($this->admin)->get(route('employees.index', ['search' => 'Aa']))->assertOk()->getContent();
        $this->assertStringContainsString(route('employees.photo', ['employee' => $withPhoto->id, 'v' => $withPhoto->updated_at->timestamp], false), html_entity_decode($html));
        $this->assertStringNotContainsString('/storage/employee-photos/', $html, 'no link through public/storage');
        $this->assertStringNotContainsString(route('employees.photo', $missingFile, false), $html, 'a missing file gets the initials, not a broken image');
        $this->actingAs($this->admin)->get(route('employees.photo', $withPhoto))->assertOk()->assertHeader('content-type', 'image/png');
        $this->actingAs($this->admin)->get(route('employees.photo', $missingFile))->assertNotFound();
        $this->actingAs($this->admin)->get(route('employees.show', $withPhoto))->assertOk()->assertSee('employees/'.$withPhoto->id.'/photo', false);
        auth()->logout();
        $this->get(route('employees.photo', $withPhoto))->assertRedirect();
        // The stored files are untouched.
        Storage::disk('public')->assertExists('employee-photos/afif.png');
    }
}
