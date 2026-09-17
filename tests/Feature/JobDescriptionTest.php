<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\JobDescription;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_job_descriptions(): void
    {
        $response = $this->get(route('job-descriptions.index'));

        $response->assertRedirect('/login');
    }

    public function test_maintenance_staff_can_view_and_create_job_descriptions_by_default(): void
    {
        // Job Descriptions is the one menu that's editable by default for
        // everyone (App\Enums\MenuKey::editableByDefault()), so staff can
        // create/edit their own job description unless an administrator
        // explicitly revokes it for them.
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->actingAs($staff)->get(route('job-descriptions.index'))->assertOk();
        $this->actingAs($staff)->get(route('job-descriptions.create'))->assertOk();
    }

    public function test_job_description_edit_can_be_revoked_for_a_specific_user(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $staff->menuPermissions()->create(['menu_key' => \App\Enums\MenuKey::JobDescriptions->value, 'can_edit' => false]);

        $this->actingAs($staff)->get(route('job-descriptions.create'))->assertForbidden();
    }

    public function test_administrator_can_still_approve_regardless_of_menu_overrides(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $position = Position::factory()->create();
        $jobDescription = JobDescription::factory()->create(['position_id' => $position->id, 'status' => 'pending_review']);

        $this->actingAs($admin)->post(route('job-descriptions.approve', $jobDescription))->assertRedirect();
        $this->assertDatabaseHas('job_descriptions', ['id' => $jobDescription->id, 'status' => 'active']);
    }

    public function test_maintenance_staff_cannot_approve_job_descriptions(): void
    {
        // Approval is a governance action, deliberately kept strictly
        // role-gated (ManageJobDescriptions) rather than covered by the
        // "editable by default" menu permission that applies to drafting.
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $position = Position::factory()->create();
        $jobDescription = JobDescription::factory()->create(['position_id' => $position->id, 'status' => 'pending_review']);

        $this->actingAs($staff)->post(route('job-descriptions.approve', $jobDescription))->assertForbidden();
    }

    public function test_manager_can_create_a_job_description_as_version_one(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $position = Position::factory()->create();

        $response = $this->actingAs($manager)->post(route('job-descriptions.store'), [
            'position_id' => $position->id,
            'job_title' => 'Maintenance Technician',
            'status' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('job_descriptions', [
            'position_id' => $position->id,
            'version' => 1,
            'status' => 'draft',
        ]);
    }

    public function test_approving_a_job_description_archives_the_previous_active_version(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $position = Position::factory()->create();

        $activeVersion = JobDescription::factory()->create([
            'position_id' => $position->id,
            'version' => 1,
            'status' => 'active',
        ]);

        $newVersion = JobDescription::factory()->create([
            'position_id' => $position->id,
            'version' => 2,
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($manager)->post(route('job-descriptions.approve', $newVersion));

        $response->assertRedirect();
        $this->assertDatabaseHas('job_descriptions', ['id' => $newVersion->id, 'status' => 'active']);
        $this->assertDatabaseHas('job_descriptions', ['id' => $activeVersion->id, 'status' => 'archived']);
    }

    public function test_new_revision_increments_version_and_starts_as_draft(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $jobDescription = JobDescription::factory()->create(['version' => 1, 'status' => 'active']);

        $response = $this->actingAs($manager)->post(route('job-descriptions.new-revision', $jobDescription));

        $response->assertRedirect();
        $this->assertDatabaseHas('job_descriptions', [
            'position_id' => $jobDescription->position_id,
            'version' => 2,
            'status' => 'draft',
        ]);
    }

    public function test_archived_job_descriptions_cannot_be_edited(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $jobDescription = JobDescription::factory()->create(['status' => 'archived']);

        $this->actingAs($manager)->get(route('job-descriptions.edit', $jobDescription))->assertForbidden();
    }
}
