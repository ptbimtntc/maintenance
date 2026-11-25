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
use App\Models\LototoTool;
use App\Models\LototoToolActivity;
use App\Models\SpareLock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * LOTOTO Activity pages: the Supervisor Feedback button (existing follow-up mechanism, one feedback per activity), the
 * Readiness Check marks that follow the stored feedback state, and 10-per-page pagination on all four pages.
 */
class LototoActivityFeedbackPaginationTest extends TestCase
{
    use RefreshDatabase;

    private LototoBoard $board;
    private User $admin;
    private User $supervisorUser;
    private User $otherSupervisorUser;
    private User $technicianUser;
    private User $guest;
    private Employee $supervisor;
    private Employee $technician;
    private Employee $otherTechnician;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $unit = BusinessUnit::factory()->create(['name' => 'Tire Cord', 'is_active' => true]);
        $active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->board = LototoBoard::query()->create([
            'business_unit_id' => $unit->id, 'division_name' => 'Maintenance', 'code' => 'mtn_tr', 'title' => 'BOARD', 'subtitle' => 'x',
            'layout' => ['board_columns' => 20], 'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true], 'is_active' => true,
        ]);
        $make = function (string $role, string $name, string $number, ?Employee $supervisor = null) use ($unit, $active) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $employee = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'lototo_number' => $number, 'full_name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@x.test', 'user_id' => $user->id, 'supervisor_id' => $supervisor?->id]);

            return [$user, $employee];
        };
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        [$this->supervisorUser, $this->supervisor] = $make(RoleName::MaintenanceSupervisor->value, 'Sari Supervisor', '101');
        [$this->otherSupervisorUser] = $make(RoleName::MaintenanceSupervisor->value, 'Rina Lain', '102');
        [$this->technicianUser, $this->technician] = $make(RoleName::MaintenanceStaff->value, 'Budi Teknisi', '07', $this->supervisor);
        [, $this->otherTechnician] = $make(RoleName::MaintenanceStaff->value, 'Cici Teknisi', '08', $this->supervisor);
        $this->guest = User::factory()->create();
        $this->guest->assignRole(RoleName::Guest->value);
        foreach ([$this->technician, $this->otherTechnician] as $i => $employee) {
            LototoBoardSlot::query()->create(['board_id' => $this->board->id, 'category' => 'technician', 'position' => $i + 1, 'is_enabled' => true, 'employee_id' => $employee->id]);
        }
    }

    /** @param array<string, mixed> $extra */
    private function activity(Employee $employee, array $extra = []): LototoActivity
    {
        return LototoActivity::query()->create(array_merge([
            'employee_id' => $employee->id, 'board' => 'mtn_tr', 'location' => 'Gearbox Line 4', 'activity' => 'Ganti bearing', 'risks' => ['listrik'], 'risk_controls' => 'LOTO',
            'knows_procedure' => true, 'follows_lototo' => true, 'uses_ppe' => true, 'safe_tools' => true, 'safe_hands' => true,
            'status' => 'completed', 'outcome' => 'finish', 'started_at' => now('UTC')->subHours(3), 'ended_at' => now('UTC')->subHour(),
        ], $extra));
    }

    /** An activity with three NO answers and the feedback requested (one feedback for all of them). */
    private function awaiting(?Employee $employee = null): LototoActivity
    {
        return $this->activity($employee ?? $this->technician, ['knows_procedure' => false, 'safe_tools' => false, 'safe_hands' => false,
            'supervisor_feedback' => 'Kunci retak', 'supervisor_follow_up_status' => 'pending']);
    }

    private function page(User $user, ?Employee $employee = null, array $query = []): string
    {
        return $this->actingAs($user)->get(route('safety.lototo.show', ['employee' => $employee ?? $this->technician, 'board' => $this->board->id] + $query))->assertOk()->getContent();
    }

    public function test_the_supervisor_sees_the_button_only_while_feedback_is_awaited_and_only_for_their_technician(): void
    {
        $activity = $this->awaiting();
        $page = $this->page($this->supervisorUser);
        $this->assertStringContainsString('data-give-supervisor-feedback', $page);
        $this->assertStringContainsString(route('safety.lototo.follow-up', $activity), $page);
        $this->assertSame(2, substr_count($page, 'data-give-supervisor-feedback'), 'desktop table and phone card, one process, one target');
        // Nobody else gets it: the technician, another supervisor, the Guest viewer, an administrator without that employee.
        foreach ([$this->technicianUser, $this->otherSupervisorUser, $this->guest, $this->admin] as $user) {
            $this->assertStringNotContainsString('data-give-supervisor-feedback', $this->page($user), $user->name);
            $this->assertStringContainsString('Menunggu feedback supervisor', $this->page($user));
        }
        // An activity that asked for no feedback, and one already answered, show no button.
        $this->activity($this->technician, ['location' => 'Plain', 'supervisor_follow_up_status' => 'not_required']);
        $this->actingAs($this->supervisorUser)->post(route('safety.lototo.follow-up.store', $activity), ['supervisor_follow_up' => 'Ganti kunci torsi.'])->assertRedirect();
        $after = $this->page($this->supervisorUser);
        $this->assertStringNotContainsString('data-give-supervisor-feedback', $after);
        $this->assertStringNotContainsString('Menunggu feedback supervisor', $after);
        $this->assertStringContainsString('Ganti kunci torsi.', $after, 'the comment stays in the Feedback column');
        $this->assertStringContainsString('Feedback teknisi:', $after);
        // A supervisor of other technicians sees no button on this one's activity.
        $this->awaiting($this->otherTechnician);
        $this->assertStringNotContainsString('data-give-supervisor-feedback', $this->page($this->otherSupervisorUser, $this->otherTechnician));
    }

    public function test_the_backend_refuses_feedback_from_anyone_but_the_technicians_supervisor(): void
    {
        $activity = $this->awaiting();
        $url = route('safety.lototo.follow-up', $activity);
        $store = route('safety.lototo.follow-up.store', $activity);
        foreach ([$this->technicianUser, $this->otherSupervisorUser, $this->guest, $this->admin] as $user) {
            $response = $this->actingAs($user)->get($url);
            // The shared Guest account is asked to log in rather than shown a 403.
            $user->is($this->guest) ? $response->assertRedirect(route('login')) : $response->assertForbidden();
            $this->actingAs($user)->post($store, ['supervisor_follow_up' => 'Tanpa hak'])->assertForbidden();
        }
        auth()->logout();
        $this->get($url)->assertRedirect();
        $this->post($store, ['supervisor_follow_up' => 'Tanpa login'])->assertRedirect();
        $this->assertSame(0, LototoSupervisorFeedback::query()->count());
        $this->assertSame('pending', $activity->fresh()->supervisor_follow_up_status);

        $this->actingAs($this->supervisorUser)->get($url)->assertOk();
        $this->actingAs($this->supervisorUser)->post($store, ['supervisor_follow_up' => 'Ganti kunci torsi.'])->assertRedirect();
        $this->assertSame(1, LototoSupervisorFeedback::query()->count());
    }

    public function test_readiness_marks_follow_the_stored_feedback_state_and_answers_never_change(): void
    {
        $activity = $this->awaiting();
        $this->activity($this->technician, ['location' => 'Semua Ya']);
        $before = $page = $this->page($this->supervisorUser);
        // Three NO answers without feedback: all red (desktop table), none green; the YES answers stay green.
        $this->assertSame(6, substr_count($before, 'data-answer-no>'), 'three red NO marks on the table and three on the phone list');
        $this->assertSame(0, substr_count($before, 'data-answer-no-followed-up'));

        // One feedback for the activity turns all three marks green; the stored answers are untouched.
        $this->actingAs($this->supervisorUser)->post(route('safety.lototo.follow-up.store', $activity), ['supervisor_follow_up' => 'Sudah ditangani.'])->assertRedirect();
        $page = $this->page($this->supervisorUser);
        $this->assertSame(0, substr_count($page, 'data-answer-no>'));
        $this->assertSame(6, substr_count($page, 'data-answer-no-followed-up'), 'three marks on the table and three on the phone list');
        $this->assertStringContainsString('bukan berarti pekerjaan aman', $page);
        $fresh = $activity->fresh();
        $this->assertSame([false, true, true, false, false], [$fresh->knows_procedure, $fresh->follows_lototo, $fresh->uses_ppe, $fresh->safe_tools, $fresh->safe_hands]);
        $this->assertSame(1, LototoSupervisorFeedback::query()->count(), 'one feedback for three NO answers');
        // The "Semua Ya" activity is untouched, and an old activity with a NO but no feedback request stays red.
        $this->activity($this->technician, ['location' => 'Lama', 'safe_tools' => false, 'supervisor_follow_up_status' => 'not_required']);
        $this->assertSame(2, substr_count($this->page($this->supervisorUser), 'data-answer-no>'), 'table + phone list');
        // The state comes from the stored data: an activity marked completed (reply stored on the row) is 'given'.
        $stored = $this->activity($this->technician, ['safe_tools' => false, 'supervisor_follow_up_status' => 'completed', 'supervisor_follow_up' => 'Lama balasan']);
        $this->assertSame('given', $stored->supervisorFeedbackState());
        $this->assertSame('awaiting', $this->awaiting()->supervisorFeedbackState());
    }

    /** @return array<int, int> */
    public static function counts(): array
    {
        return [[0], [5], [10], [11], [20], [25]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('counts')]
    public function test_technician_and_supervisor_activity_pages_show_ten_per_page(int $count): void
    {
        foreach (array_slice(range(1, 30), 0, $count) as $i) {
            $this->activity($this->technician, ['location' => sprintf('Loc-%02d', $i), 'started_at' => now('UTC')->subMinutes($i)]);
        }
        $this->activity($this->otherTechnician, ['location' => 'Orang Lain']);
        $seen = [];
        $pages = max(1, (int) ceil($count / 10));
        foreach (range(1, $pages) as $p) {
            $html = $this->page($this->technicianUser, null, ['page' => $p]);
            preg_match_all('/<h3 class="break-words text-base font-bold leading-snug text-neutral-900">(Loc-\d+)<\/h3>/', $html, $m);
            $this->assertLessThanOrEqual(10, count($m[1]));
            $this->assertSame($p < $pages || $count % 10 === 0 ? min(10, $count) : $count % 10, count($m[1]) ?: ($count === 0 ? 0 : -1), "page {$p} of {$count}");
            $seen = array_merge($seen, $m[1]);
            $this->assertStringNotContainsString('Orang Lain', $html);
            $this->assertSame($count > 10, str_contains($html, 'data-activity-pagination'), 'pagination only when more than 10');
            if ($count > 10) {
                $this->assertStringContainsString('Halaman '.$p.' dari '.$pages, $html);
                $this->assertStringContainsString('board='.$this->board->id, html_entity_decode($html), 'board kept in the page links');
                $this->assertStringContainsString('Previous', $html);
                $this->assertStringContainsString('Next', $html);
                $this->assertStringContainsString('aria-label="Halaman '.($p === 1 ? 2 : 1).'"', $html);
            }
        }
        $this->assertSame($count, count($seen));
        $this->assertSame($count, count(array_unique($seen)), 'no duplicates and none skipped across pages');
        if ($count > 10) {
            $this->assertSame(sprintf('Loc-%02d', 1), $seen[0], 'newest first');
            // A page past the end goes to the last page instead of an empty list.
            $this->actingAs($this->technicianUser)->get(route('safety.lototo.show', ['employee' => $this->technician, 'board' => $this->board->id, 'page' => 99]))
                ->assertRedirect();
        }
        // A supervisor's own page paginates the same way.
        foreach (array_slice(range(1, 30), 0, min($count, 12)) as $i) {
            $this->activity($this->supervisor, ['location' => sprintf('Sup-%02d', $i), 'started_at' => now('UTC')->subMinutes($i)]);
        }
        $page = $this->actingAs($this->supervisorUser)->get(route('safety.lototo.show', ['employee' => $this->supervisor, 'board' => $this->board->id]))->assertOk()->getContent();
        preg_match_all('/>(Sup-\d+)<\/h3>/', $page, $sm);
        $this->assertSame(min($count, 10, 12), count($sm[1]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('counts')]
    public function test_spare_activity_page_shows_ten_per_page(int $count): void
    {
        $spare = SpareLock::query()->create(['board_id' => $this->board->id, 'code' => 'SP-01', 'is_active' => true]);
        foreach (array_slice(range(1, 30), 0, $count) as $i) {
            $this->activity($this->technician, ['location' => sprintf('Spare-%02d', $i), 'spare_lock_id' => $spare->id, 'started_at' => now('UTC')->subMinutes($i)]);
        }
        $seen = [];
        $pages = max(1, (int) ceil($count / 10));
        foreach (range(1, $pages) as $p) {
            $html = $this->actingAs($this->guest)->get(route('safety.lototo.spare-locks.show', ['spareLock' => $spare, 'page' => $p]))->assertOk()->getContent();
            preg_match_all('/>(Spare-\d+)<\/h3>/', $html, $m);
            $this->assertLessThanOrEqual(10, count($m[1]));
            $seen = array_merge($seen, $m[1]);
            $this->assertSame($count > 10, str_contains($html, 'data-activity-pagination'));
        }
        $this->assertSame($count, count(array_unique($seen)));
        if ($count > 10) {
            $this->actingAs($this->guest)->get(route('safety.lototo.spare-locks.show', ['spareLock' => $spare, 'page' => 99]))->assertRedirect();
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('counts')]
    public function test_tools_activity_page_shows_ten_per_page(int $count): void
    {
        $tool = LototoTool::query()->create(['board_id' => $this->board->id, 'code' => 'TL-01', 'name' => 'Kunci Torsi', 'is_active' => true]);
        foreach (array_slice(range(1, 30), 0, $count) as $i) {
            LototoToolActivity::query()->create(['tool_id' => $tool->id, 'board_id' => $this->board->id, 'location' => sprintf('Tool-%02d', $i), 'status' => 'completed',
                'started_by' => $this->admin->id, 'started_at' => now('UTC')->subMinutes($i + 5), 'ended_at' => now('UTC')->subMinutes($i)]);
        }
        $seen = [];
        $pages = max(1, (int) ceil($count / 10));
        foreach (range(1, $pages) as $p) {
            $html = $this->actingAs($this->admin)->get(route('safety.lototo.tools.form', ['tool' => $tool, 'board' => $this->board->id, 'page' => $p]))->assertOk()->getContent();
            preg_match_all('/>(Tool-\d+)</', $html, $m);
            $unique = array_values(array_unique($m[1]));
            $this->assertLessThanOrEqual(10, count($unique));
            $seen = array_merge($seen, $unique);
            $this->assertSame($count > 10, str_contains($html, 'data-activity-pagination'));
        }
        $this->assertSame($count, count(array_unique($seen)), 'no duplicates and none skipped');
        if ($count > 10) {
            $this->actingAs($this->admin)->get(route('safety.lototo.tools.form', ['tool' => $tool, 'board' => $this->board->id, 'page' => 99]))->assertRedirect();
        }
    }
}
