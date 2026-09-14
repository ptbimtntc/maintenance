<?php

namespace Tests\Feature;

use App\Models\CompetencyLevel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSkillAssessment;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_view_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Maintenance Departments');
    }

    public function test_dashboard_shows_real_department_counts(): void
    {
        Department::factory()->count(3)->create(['is_active' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('3');
    }

    public function test_dashboard_computes_average_competency_score_from_latest_assessments(): void
    {
        $employee = Employee::factory()->create();
        $skill = Skill::factory()->create();
        $levelTwo = CompetencyLevel::factory()->create(['level_number' => 2]);
        $levelFour = CompetencyLevel::factory()->create(['level_number' => 4]);

        // Older assessment should be superseded by the newer one below.
        EmployeeSkillAssessment::factory()->create([
            'employee_id' => $employee->id,
            'skill_id' => $skill->id,
            'competency_level_id' => $levelTwo->id,
            'assessment_date' => now()->subMonth(),
        ]);
        EmployeeSkillAssessment::factory()->create([
            'employee_id' => $employee->id,
            'skill_id' => $skill->id,
            'competency_level_id' => $levelFour->id,
            'assessment_date' => now(),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('4');
    }
}
