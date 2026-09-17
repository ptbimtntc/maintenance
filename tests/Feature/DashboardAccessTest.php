<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\CompetencyLevel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSkillAssessment;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
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

    public function test_stat_cards_only_link_to_modules_the_viewer_is_permitted_to_open(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($staff)->get('/dashboard');

        $response->assertOk();
        // Staff lacks ViewCompetencyGap and ManageMasterData - those cards
        // must render without a link rather than pointing somewhere that
        // would 403 if clicked.
        $response->assertDontSee('aria-label="Employees with Competency Gaps"', false);
        $response->assertDontSee('aria-label="Maintenance Departments"', false);
        // Staff does hold ViewCertificates, so that card should still link.
        $response->assertSee('aria-label="Certificates Expiring Soon"', false);
    }

    public function test_an_administrator_sees_links_on_every_stat_card(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('aria-label="Employees with Competency Gaps"', false);
        $response->assertSee('aria-label="Maintenance Departments"', false);
    }
}
