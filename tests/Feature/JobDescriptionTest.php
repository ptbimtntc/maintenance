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

    public function test_maintenance_staff_can_view_but_not_create_job_descriptions(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->actingAs($staff)->get(route('job-descriptions.index'))->assertOk();
        $this->actingAs($staff)->get(route('job-descriptions.create'))->assertForbidden();
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
