<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\CompetencyLevel;
use App\Models\Employee;
use App\Models\EmployeeSkillAssessment;
use App\Models\Position;
use App\Models\PositionSkillRequirement;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_the_matrix_can_be_filtered_by_more_than_one_position_at_once(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        Skill::factory()->create(['is_active' => true]);

        $positionA = Position::factory()->create(['title' => 'Mechanical Technician']);
        $positionB = Position::factory()->create(['title' => 'Electrical Technician']);
        $positionC = Position::factory()->create(['title' => 'Supervisor']);

        Employee::factory()->create(['full_name' => 'Tech A', 'position_id' => $positionA->id]);
        Employee::factory()->create(['full_name' => 'Tech B', 'position_id' => $positionB->id]);
        Employee::factory()->create(['full_name' => 'Tech C', 'position_id' => $positionC->id]);

        $response = $this->actingAs($admin)->get(route('skill-matrix.index', [
            'position_id' => [$positionA->id, $positionB->id],
        ]));

        $response->assertOk();
        $response->assertSee('Tech A')->assertSee('Tech B')->assertDontSee('Tech C');
    }

    public function test_guests_cannot_view_the_skill_matrix(): void
    {
        $response = $this->get(route('skill-matrix.index'));

        $response->assertRedirect('/guest-login');
    }

    public function test_authorized_users_see_a_gap_when_current_level_is_below_required(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);

        $position = Position::factory()->create();
        $skill = Skill::factory()->create(['name' => 'Mechanical Maintenance']);
        $levelTwo = CompetencyLevel::factory()->create(['level_number' => 2]);
        $levelThree = CompetencyLevel::factory()->create(['level_number' => 3]);

        PositionSkillRequirement::factory()->create([
            'position_id' => $position->id,
            'skill_id' => $skill->id,
            'required_competency_level_id' => $levelThree->id,
        ]);

        $employee = Employee::factory()->create(['position_id' => $position->id]);
        EmployeeSkillAssessment::factory()->create([
            'employee_id' => $employee->id,
            'skill_id' => $skill->id,
            'competency_level_id' => $levelTwo->id,
        ]);

        $response = $this->actingAs($user)->get(route('skill-matrix.index'));

        $response->assertOk();
        $response->assertSee('Development Required');
    }

    public function test_unassessed_required_skill_is_shown_as_not_applicable_rather_than_meeting_requirement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Administrator->value);

        $position = Position::factory()->create();
        $skill = Skill::factory()->create();
        $level = CompetencyLevel::factory()->create(['level_number' => 3]);

        PositionSkillRequirement::factory()->create([
            'position_id' => $position->id,
            'skill_id' => $skill->id,
            'required_competency_level_id' => $level->id,
        ]);

        Employee::factory()->create(['position_id' => $position->id]);

        $response = $this->actingAs($user)->get(route('skill-matrix.index'));

        $response->assertOk();
        $response->assertSee('Assessment Incomplete');
        $response->assertViewHas('matrix', function ($matrix) {
            return $matrix->first()['overall_status'] === 'incomplete';
        });
    }
}
