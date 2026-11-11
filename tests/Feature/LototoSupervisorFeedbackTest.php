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
use App\Notifications\LototoSafetyAlert;
use App\Support\LototoFollowUpLink;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Supervisor feedback of a LOTOTO activity: the notification (application and email) when the checklist holds a NO
 * or the technician wrote feedback, the signed Supervisor Feedback page of the email link, and the replies on the
 * technician's LOTOTO Activity page. No real email is sent (Notification::fake).
 */
class LototoSupervisorFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private LototoBoard $board;
    private User $admin;
    private User $supervisorUser;
    private Employee $supervisor;
    private Employee $technician;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $unit = BusinessUnit::factory()->create(['name' => 'Tire Cord', 'is_active' => true]);
        $active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $unit->id, 'division_name' => 'Maintenance', 'code' => 'mtn_tr', 'title' => 'BOARD', 'subtitle' => 'x',
            'layout' => ['board_columns' => 20], 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $this->supervisorUser = User::factory()->create(['email' => 'spv@example.test']);
        $this->supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $this->supervisor = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'lototo_number' => '101',
            'full_name' => 'Sari Supervisor', 'email' => 'sari.supervisor@company.test', 'user_id' => $this->supervisorUser->id]);
        $this->technician = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'lototo_number' => '07',
            'full_name' => 'Budi Teknisi', 'supervisor_id' => $this->supervisor->id]);
        LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'technician', 'position' => 7, 'is_enabled' => true, 'employee_id' => $this->technician->id]);
    }

    public function test_all_yes_and_no_feedback_sends_nothing(): void
    {
        $activity = $this->in();
        Notification::assertNothingSent();
        $this->assertSame('not_required', $activity->supervisor_follow_up_status);
    }

    public function test_a_no_or_feedback_or_both_send_one_notification_by_email_to_the_supervisor_only(): void
    {
        $other = User::factory()->create(['email' => 'other@example.test']);
        $other->assignRole(RoleName::MaintenanceSupervisor->value);

        foreach ([
            'one NO' => ['safe_tools' => 'tidak'],
            'feedback' => ['supervisor_feedback' => 'Lampu area kurang terang.'],
            'NO and feedback' => ['safe_hands' => 'tidak', 'uses_ppe' => 'tidak', 'supervisor_feedback' => 'APD robek.'],
        ] as $case => $answers) {
            Notification::fake();
            $activity = $this->in($answers);
            $this->assertSame('pending', $activity->supervisor_follow_up_status, $case);
            // In the application: the supervisor's account, once.
            Notification::assertSentToTimes($this->supervisorUser, LototoSafetyAlert::class, 1);
            Notification::assertSentTo($this->supervisorUser, LototoSafetyAlert::class, fn ($n, array $channels) => $channels === ['database']);
            // By email: once, to the supervisor's address in employees (not the account's address).
            Notification::assertSentOnDemandTimes(LototoSafetyAlert::class, 1);
            Notification::assertSentOnDemand(LototoSafetyAlert::class, fn ($n, array $channels, $notifiable) => $channels === ['mail']
                && $notifiable->routes === ['mail' => 'sari.supervisor@company.test']);
            Notification::assertNotSentTo($other, LototoSafetyAlert::class);
            $this->out($activity);
        }
    }

    public function test_the_email_follows_the_supervisor_data_in_employees(): void
    {
        $sentTo = function (): array {
            $to = [];
            Notification::assertSentOnDemand(LototoSafetyAlert::class, function ($n, $channels, $notifiable) use (&$to) {
                $to[] = $notifiable->routes['mail'];

                return true;
            });

            return $to;
        };

        // No address in employees: the address of the supervisor's account.
        $this->supervisor->update(['email' => null]);
        $this->out($this->in(['safe_tools' => 'tidak']));
        $this->assertSame(['spv@example.test'], $sentTo());

        // A supervisor without an account still gets the email (no in-app notification then).
        Notification::fake();
        $this->supervisor->update(['email' => 'sari.supervisor@company.test', 'user_id' => null]);
        $this->out($this->in(['safe_tools' => 'tidak']));
        $this->assertSame(['sari.supervisor@company.test'], $sentTo());
        Notification::assertNotSentTo($this->supervisorUser, LototoSafetyAlert::class);

        // Neither an account nor an email: the IN is refused with a clear message, nothing is saved.
        Notification::fake();
        $this->supervisor->update(['email' => null]);
        $count = LototoActivity::query()->count();
        $this->actingAs($this->admin)->post(route('safety.lototo.form.store'), [
            'lototo_number' => '07', 'employee_id' => $this->technician->id, 'board_id' => $this->board->id, 'lock_status' => 'in', 'location' => 'L', 'activity' => 'A',
            'risks' => ['kesetrum'], 'risk_controls' => 'c', 'knows_procedure' => 'ya', 'follows_lototo' => 'ya', 'uses_ppe' => 'ya', 'safe_tools' => 'tidak', 'safe_hands' => 'ya',
        ])->assertSessionHasErrors(['supervisor_feedback' => 'Data atasan, akun, atau email supervisor belum tersedia. Hubungi administrator agar peringatan LOTOTO dapat diteruskan.']);
        $this->assertSame($count, LototoActivity::query()->count());
        Notification::assertNothingSent();

        // An employee without a supervisor gets no email to some other supervisor either.
        $this->technician->update(['supervisor_id' => null]);
        $this->actingAs($this->admin)->post(route('safety.lototo.form.store'), [
            'lototo_number' => '07', 'employee_id' => $this->technician->id, 'board_id' => $this->board->id, 'lock_status' => 'in', 'location' => 'L', 'activity' => 'A',
            'risks' => ['kesetrum'], 'risk_controls' => 'c', 'knows_procedure' => 'ya', 'follows_lototo' => 'ya', 'uses_ppe' => 'ya', 'safe_tools' => 'tidak', 'safe_hands' => 'ya',
        ])->assertSessionHasErrors('supervisor_feedback');
        Notification::assertNothingSent();
    }

    public function test_the_email_holds_the_activity_the_answers_in_red_when_no_the_feedback_and_the_link(): void
    {
        $activity = $this->in(['safe_tools' => 'tidak', 'supervisor_feedback' => 'Kunci torsi retak.']);
        $html = null;
        Notification::assertSentOnDemand(LototoSafetyAlert::class, function (LototoSafetyAlert $notification, array $channels, $notifiable) use (&$html) {
            $mail = $notification->toMail($notifiable);
            $this->assertStringContainsString('LOTOTO & LMRA FEEDBACK', $mail->subject);
            $html = (string) $mail->render();

            return true;
        });
        foreach (['LOTOTO &amp; LMRA FEEDBACK', 'Perlu feedback / follow-up supervisor', 'Budi Teknisi', '>07<', 'Test machine', 'Test maintenance work', 'Kunci torsi retak.', 'LINK TO FOLLOW UP',
            '1. Saya tahu bagaimana melakukan pekerjaan ini', '5. Jari / tangan saya AMAN (tidak dalam LoF)'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        // Answer 4 (NO) is red; answer 1 (YES) is not.
        $this->assertMatchesRegularExpression('/background:#fef2f2;color:#b91c1c;[^"]*">\s*4\. Saya memiliki tools.*?background:#dc2626;color:#ffffff;">\s*TIDAK/s', $html);
        $this->assertDoesNotMatchRegularExpression('/#b91c1c;[^"]*">\s*1\. Saya tahu/s', $html);
        // The link is the signed Supervisor Feedback page of this activity for this supervisor.
        preg_match('/href="([^"]+respond[^"]+)"/', $html, $m);
        $link = html_entity_decode($m[1]);
        $this->assertStringContainsString('/safety/lototo/follow-up/'.$activity->id.'/respond?', $link);
        $this->assertStringContainsString('supervisor='.$this->supervisor->id, $link);
        $this->get($link)->assertOk()->assertSee('Supervisor Feedback');
    }

    public function test_the_supervisor_opens_the_link_replies_and_the_reply_shows_on_the_technicians_activity(): void
    {
        $activity = $this->in(['safe_tools' => 'tidak', 'supervisor_feedback' => 'Kunci torsi retak.']);
        $link = LototoFollowUpLink::url($activity, $this->supervisor);

        // Before a reply the technician's page says it is awaited.
        $this->actingAs($this->admin)->get($this->activityPage())->assertSee('Menunggu feedback supervisor');
        auth()->logout();

        // The page shows the technician's activity read only, then Supervisor Comment / Feedback.
        $page = $this->get($link)->assertOk()->getContent();
        foreach (['Budi Teknisi', 'Test machine', 'Test maintenance work', 'Kunci torsi retak.', 'Supervisor Comment / Feedback', 'Send Feedback', 'Menunggu feedback supervisor.'] as $text) {
            $this->assertStringContainsString($text, $page);
        }
        $this->assertStringNotContainsString('name="location"', $page);
        $this->assertStringNotContainsString('name="safe_tools"', $page);

        // The form posts to its own signed address and comes back to the page, which says it was sent.
        preg_match('/<form method="POST" action="([^"]+)"[^>]*data-supervisor-feedback-form/', $page, $form);
        $action = html_entity_decode($form[1]);
        $back = $this->post($action, ['supervisor_follow_up' => 'Ganti kunci torsi sebelum lanjut.'])->assertRedirect()->headers->get('Location');
        $this->get($back)->assertOk()->assertSee('Feedback supervisor berhasil dikirim.')->assertSee('Ganti kunci torsi sebelum lanjut.');
        $reply = LototoSupervisorFeedback::query()->sole();
        $this->assertSame([$activity->id, $this->supervisor->id, $this->supervisorUser->id, 'Ganti kunci torsi sebelum lanjut.'],
            [$reply->lototo_activity_id, $reply->supervisor_employee_id, $reply->user_id, $reply->comment]);
        $activity->refresh();
        $this->assertSame(['completed', 'Ganti kunci torsi sebelum lanjut.', $this->supervisorUser->id], [$activity->supervisor_follow_up_status, $activity->supervisor_follow_up, $activity->supervisor_follow_up_by]);
        // The technician's own feedback is untouched.
        $this->assertSame('Kunci torsi retak.', $activity->supervisor_feedback);

        // The same reply sent again (double click, resent email form) is stored once; a further reply is kept too.
        $this->post($action, ['supervisor_follow_up' => 'Ganti kunci torsi sebelum lanjut.'])->assertRedirect();
        $this->assertSame(1, LototoSupervisorFeedback::query()->count());
        $this->post($action, ['supervisor_follow_up' => 'Sudah dicek, kunci baru dipakai.'])->assertRedirect();
        $this->assertSame(['Ganti kunci torsi sebelum lanjut.', 'Sudah dicek, kunci baru dipakai.'], LototoSupervisorFeedback::query()->orderBy('id')->pluck('comment')->all());
        $this->assertSame('Sudah dicek, kunci baru dipakai.', $activity->fresh()->supervisor_follow_up);

        // On the technician's LOTOTO Activity: activity, checklist, technician feedback, then both replies with name and time.
        $html = $this->actingAs($this->admin)->get($this->activityPage())->assertOk()->getContent();
        $this->assertStringNotContainsString('Menunggu feedback supervisor', $html);
        $this->assertMatchesRegularExpression('/Feedback teknisi:<\/span> Kunci torsi retak\..*?Feedback supervisor:.*?Ganti kunci torsi sebelum lanjut\..*?Sari Supervisor · \d{2} \w{3} \d{4} \d{2}:\d{2}.*?Sudah dicek, kunci baru dipakai\./s', $html);
        // The application's follow-up page (signed in as the supervisor) shows the same history.
        $this->actingAs($this->supervisorUser)->get(route('safety.lototo.follow-up', $activity))->assertOk()
            ->assertSee('Ganti kunci torsi sebelum lanjut.')->assertSee('Sudah dicek, kunci baru dipakai.');
    }

    public function test_an_invalid_expired_or_foreign_link_shows_and_saves_nothing(): void
    {
        $activity = $this->in(['safe_tools' => 'tidak']);
        $link = LototoFollowUpLink::url($activity, $this->supervisor);
        $refused = function (string $url, string $case) {
            $this->get($url)->assertForbidden()->assertDontSee('Test machine');
            $this->post($url, ['supervisor_follow_up' => 'X'])->assertForbidden();
            $this->assertSame(0, LototoSupervisorFeedback::query()->count(), $case);
        };

        $refused(preg_replace('/signature=\w+/', 'signature='.str_repeat('0', 64), $link), 'wrong signature');
        $refused(preg_replace('/&?signature=\w+/', '', $link), 'no signature');
        $refused(str_replace('supervisor='.$this->supervisor->id, 'supervisor='.$this->technician->id, $link), 'another supervisor in the link');
        $other = $this->in(['safe_hands' => 'tidak'], Employee::factory()->create(['business_unit_id' => $this->technician->business_unit_id,
            'employment_status_id' => $this->technician->employment_status_id, 'lototo_number' => '08', 'supervisor_id' => $this->supervisor->id]));
        $refused(str_replace('/follow-up/'.$activity->id.'/', '/follow-up/'.$other->id.'/', $link), 'another activity');

        // Someone signed in who is not this supervisor cannot use it, nor send feedback in the supervisor's name.
        $intruder = User::factory()->create();
        $intruder->assignRole(RoleName::MaintenanceSupervisor->value);
        Employee::factory()->create(['business_unit_id' => $this->technician->business_unit_id, 'employment_status_id' => $this->technician->employment_status_id, 'user_id' => $intruder->id]);
        $this->actingAs($intruder)->get($link)->assertForbidden();
        $this->actingAs($intruder)->post($link, ['supervisor_follow_up' => 'Atas nama orang lain'])->assertForbidden();
        $this->actingAs($intruder)->post(route('safety.lototo.follow-up.store', $activity), ['supervisor_follow_up' => 'Atas nama orang lain'])->assertForbidden();
        auth()->logout();
        $this->assertSame(0, LototoSupervisorFeedback::query()->count());

        // When the technician gets another supervisor, the old supervisor's link stops working.
        $this->technician->update(['supervisor_id' => Employee::factory()->create(['business_unit_id' => $this->technician->business_unit_id])->id]);
        $refused($link, 'supervisor changed');
        $this->technician->update(['supervisor_id' => $this->supervisor->id]);

        // After the link's validity it opens nothing.
        $this->travel(LototoFollowUpLink::VALID_DAYS + 1)->days();
        $refused($link, 'expired');
        $this->assertSame('pending', $activity->fresh()->supervisor_follow_up_status);
    }

    public function test_the_migration_keeps_replies_saved_before_as_history(): void
    {
        $migration = require database_path('migrations/2026_11_10_000000_create_lototo_supervisor_feedbacks_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('lototo_supervisor_feedbacks'));
        $activity = $this->in(['safe_tools' => 'tidak']);
        $activity->update(['supervisor_follow_up' => 'Balasan lama', 'supervisor_follow_up_by' => $this->supervisorUser->id,
            'supervisor_follow_up_at' => now('UTC'), 'supervisor_follow_up_status' => 'completed']);
        // Without the table the page shows the reply stored on the activity.
        $this->actingAs($this->admin)->get($this->activityPage())->assertOk()->assertSee('Balasan lama');

        $migration->up();
        $migration->up();
        $row = DB::table('lototo_supervisor_feedbacks')->sole();
        $this->assertSame([$activity->id, $this->supervisor->id, $this->supervisorUser->id, 'Balasan lama'], [(int) $row->lototo_activity_id, (int) $row->supervisor_employee_id, (int) $row->user_id, $row->comment]);
        // An activity with replies cannot be deleted (the history stays).
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('lototo_activities')->where('id', $activity->id)->delete();
    }

    /** An IN by the administrator for the technician, with the given answers (all YES by default). */
    private function in(array $answers = [], ?Employee $employee = null): LototoActivity
    {
        $employee ??= $this->technician;
        $this->actingAs($this->admin)->post(route('safety.lototo.form.store'), [
            'lototo_number' => $employee->lototo_number, 'employee_id' => $employee->id, 'lototo_board' => $this->board->code, 'board_id' => $this->board->id,
            'lock_status' => 'in', 'location' => 'Test machine', 'activity' => 'Test maintenance work', 'risks' => ['kesetrum'], 'risk_controls' => 'Isolate',
            'knows_procedure' => 'ya', 'follows_lototo' => 'ya', 'uses_ppe' => 'ya', 'safe_tools' => 'ya', 'safe_hands' => 'ya', ...$answers,
        ])->assertSessionHasNoErrors();
        auth()->logout();

        return $employee->lototoActivities()->latest('id')->firstOrFail();
    }

    private function out(LototoActivity $activity): void
    {
        $this->actingAs($this->admin)->post(route('safety.lototo.form.store'), [
            'lototo_number' => $activity->employee->lototo_number, 'employee_id' => $activity->employee_id, 'board_id' => $this->board->id,
            'lock_status' => 'out', 'outcome' => 'finish',
        ])->assertSessionHasNoErrors();
        auth()->logout();
    }

    private function activityPage(): string
    {
        return route('safety.lototo.show', ['employee' => $this->technician, 'board' => $this->board->id]);
    }
}
