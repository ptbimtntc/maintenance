<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Skill;
use App\Models\TrainingProgram;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_training_programs(): void
    {
        $response = $this->get(route('training.programs.index'));

        $response->assertRedirect('/guest-login');
    }

    public function test_the_programs_list_can_be_filtered_by_more_than_one_status(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        TrainingProgram::factory()->create(['title' => 'Draft Program', 'status' => 'draft']);
        TrainingProgram::factory()->create(['title' => 'Active Program', 'status' => 'active']);
        TrainingProgram::factory()->create(['title' => 'Inactive Program', 'status' => 'inactive']);

        $response = $this->actingAs($manager)->get(route('training.programs.index', [
            'status' => ['draft', 'active'],
        ]));

        $response->assertOk();
        $response->assertSee('Draft Program')->assertSee('Active Program')->assertDontSee('Inactive Program');
    }

    public function test_maintenance_staff_cannot_open_or_create_programs(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->actingAs($staff)->get(route('training.programs.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('training.programs.create'))->assertForbidden();
    }

    public function test_manager_can_create_a_training_program_with_related_skills(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $skill = Skill::factory()->create();

        $response = $this->actingAs($manager)->post(route('training.programs.store'), [
            'title' => 'PLC Fundamentals',
            'status' => 'active',
            'skills' => [$skill->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('training_programs', ['title' => 'PLC Fundamentals']);
        $program = TrainingProgram::where('title', 'PLC Fundamentals')->first();
        $this->assertTrue($program->skills->contains($skill));
    }

    public function test_supervisor_cannot_manage_training_programs(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole(RoleName::MaintenanceSupervisor->value);

        $this->actingAs($supervisor)->get(route('training.programs.index'))->assertForbidden();

        $program = TrainingProgram::factory()->create();
        $this->actingAs($supervisor)->get(route('training.programs.edit', $program))->assertForbidden();
    }
}
