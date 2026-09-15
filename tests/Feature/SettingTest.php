<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Certificate;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_administrators_can_manage_settings(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $this->actingAs($manager)->get(route('settings.edit'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $this->actingAs($admin)->get(route('settings.edit'))->assertOk();
    }

    public function test_updating_the_expiring_soon_window_changes_certificate_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $certificate = Certificate::factory()->create([
            'expiry_date' => now()->addDays(20),
            'verification_status' => 'verified',
        ]);

        // Default window (60 days) - 20 days out counts as expiring soon.
        $this->assertSame('expiring_soon', $certificate->status());

        $this->actingAs($admin)->put(route('settings.update'), [
            'certificate_expiring_soon_days' => 10,
            'training_reminder_days_before' => 7,
        ]);

        $this->assertSame('valid', $certificate->fresh()->status());
    }

    public function test_settings_default_when_never_configured(): void
    {
        $this->assertSame(60, Setting::getInt('certificate_expiring_soon_days', 60));
    }
}
