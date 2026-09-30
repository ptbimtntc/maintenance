<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Mail\CombinedModuleReport;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Models\TrainingRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendCombinedReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_it_emails_everyone_who_can_view_reports(): void
    {
        Mail::fake();

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $employee = Employee::factory()->create();
        TrainingRecord::factory()->create(['employee_id' => $employee->id, 'training_date' => now()->subMonthNoOverflow()]);
        OvertimeEntry::factory()->create([
            'employee_id' => $employee->id,
            'start_at' => now()->subMonthNoOverflow()->startOfMonth()->addDay(),
            'end_at' => now()->subMonthNoOverflow()->startOfMonth()->addDay()->addHours(2),
        ]);

        $this->artisan('app:send-combined-report')->assertSuccessful();

        Mail::assertSent(CombinedModuleReport::class, function (CombinedModuleReport $mail) use ($manager) {
            return $mail->hasTo($manager->email);
        });
        Mail::assertSent(CombinedModuleReport::class, 1);
    }

    public function test_it_sends_nothing_when_nobody_holds_view_reports(): void
    {
        Mail::fake();

        $this->artisan('app:send-combined-report')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_cleans_up_the_temporary_attachment_file(): void
    {
        Mail::fake();

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $this->artisan('app:send-combined-report')->assertSuccessful();

        $leftoverFiles = glob(storage_path('app/combined-report-*.xlsx'));

        $this->assertEmpty($leftoverFiles);
    }
}
