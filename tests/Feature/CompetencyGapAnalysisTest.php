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

class CompetencyGapAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_the_gap_analysis(): void
    {
        $response = $this->get(route('competency-gap-analysis.index'));

        $response->assertRedirect('/login');
    }

    public function test_maintenance_staff_cannot_view_the_gap_analysis(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($staff)->get(route('competency-gap-analysis.index'));

        $response->assertForbidden();
    }

    public function test_manager_sees_skills_and_positions_ranked_by_gap_count(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $position = Position::factory()->create(['title' => 'Reliability Engineer']);
        $skill = Skill::factory()->create(['name' => 'Vibration Analysis']);
        $levelTwo = CompetencyLevel::factory()->create(['level_number' => 2]);
        $levelFour = CompetencyLevel::factory()->create(['level_number' => 4]);

        PositionSkillRequirement::factory()->create([
            'position_id' => $position->id,
            'skill_id' => $skill->id,
            'required_competency_level_id' => $levelFour->id,
        ]);

        $employeeWithGap = Employee::factory()->create(['position_id' => $position->id]);
        EmployeeSkillAssessment::factory()->create([
            'employee_id' => $employeeWithGap->id,
            'skill_id' => $skill->id,
            'competency_level_id' => $levelTwo->id,
        ]);

        $response = $this->actingAs($manager)->get(route('competency-gap-analysis.index'));

        $response->assertOk();
        $response->assertSee('Vibration Analysis');
        $response->assertSee('Reliability Engineer');
        $response->assertSee($employeeWithGap->full_name);
        $response->assertViewHas('summary', fn ($summary) => $summary['employees_with_gaps'] === 1);
    }

    public function test_employees_without_any_gap_are_excluded_from_the_gap_list(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $position = Position::factory()->create();
        $skill = Skill::factory()->create();
        $level = CompetencyLevel::factory()->create(['level_number' => 3]);

        PositionSkillRequirement::factory()->create([
            'position_id' => $position->id,
            'skill_id' => $skill->id,
            'required_competency_level_id' => $level->id,
        ]);

        $employeeMeetingRequirement = Employee::factory()->create(['position_id' => $position->id]);
        EmployeeSkillAssessment::factory()->create([
            'employee_id' => $employeeMeetingRequirement->id,
            'skill_id' => $skill->id,
            'competency_level_id' => $level->id,
        ]);

        $response = $this->actingAs($manager)->get(route('competency-gap-analysis.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn ($summary) => $summary['employees_with_gaps'] === 0);
        $response->assertViewHas('employeesWithGaps', fn ($list) => $list->isEmpty());
    }
}
