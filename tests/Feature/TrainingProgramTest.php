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

        $response->assertRedirect('/login');
    }

    public function test_maintenance_staff_can_view_but_not_create_programs(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->actingAs($staff)->get(route('training.programs.index'))->assertOk();
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

    public function test_supervisor_can_view_but_not_manage_training_programs(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole(RoleName::MaintenanceSupervisor->value);

        $this->actingAs($supervisor)->get(route('training.programs.index'))->assertOk();

        $program = TrainingProgram::factory()->create();
        $this->actingAs($supervisor)->get(route('training.programs.edit', $program))->assertForbidden();
    }
}
