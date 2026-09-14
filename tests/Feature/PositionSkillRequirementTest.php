<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\CompetencyLevel;
use App\Models\Position;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionSkillRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_maintenance_staff_cannot_manage_position_skill_requirements(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($staff)->get(route('skills.positions.index'));

        $response->assertForbidden();
    }

    public function test_manager_can_add_a_skill_requirement_to_a_position(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $position = Position::factory()->create();
        $skill = Skill::factory()->create();
        $level = CompetencyLevel::factory()->create();

        $response = $this->actingAs($manager)->post(route('skills.positions.requirements.store', $position), [
            'skill_id' => $skill->id,
            'required_competency_level_id' => $level->id,
        ]);

        $response->assertRedirect(route('skills.positions.edit', $position));
        $this->assertDatabaseHas('position_skill_requirements', [
            'position_id' => $position->id,
            'skill_id' => $skill->id,
        ]);
    }

    public function test_the_same_skill_cannot_be_required_twice_on_one_position(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $position = Position::factory()->create();
        $skill = Skill::factory()->create();
        $level = CompetencyLevel::factory()->create();

        $position->skillRequirements()->create([
            'skill_id' => $skill->id,
            'required_competency_level_id' => $level->id,
        ]);

        $response = $this->actingAs($manager)->post(route('skills.positions.requirements.store', $position), [
            'skill_id' => $skill->id,
            'required_competency_level_id' => $level->id,
        ]);

        $response->assertSessionHasErrors('skill_id');
    }
}
