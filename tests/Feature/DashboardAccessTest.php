<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\CompetencyLevel;
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
        $response->assertSee('Competency Health Overview');
        $response->assertSee('Workforce Mix');
    }

    public function test_dashboard_shows_real_employee_counts(): void
    {
        Employee::factory()->count(3)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('kpis', fn ($kpis) => $kpis['total_employees'] === 3);
    }

    public function test_the_business_unit_filter_narrows_every_employee_derived_figure(): void
    {
        $businessUnit = \App\Models\BusinessUnit::factory()->create();
        Employee::factory()->count(2)->create(['business_unit_id' => $businessUnit->id]);
        Employee::factory()->count(5)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard?business_unit_id='.$businessUnit->id);

        $response->assertOk();
        $response->assertViewHas('kpis', fn ($kpis) => $kpis['total_employees'] === 2);
    }

    public function test_competency_health_overview_reflects_the_same_gap_definition_as_skill_matrix(): void
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
        // No position skill requirements exist for this employee, so it
        // falls under "no requirements defined" - the same classification
        // Employee::skillGapRows() would produce.
        $response->assertViewHas('competencyBreakdown', fn ($breakdown) => $breakdown['no_requirements'] === 1);
    }

    public function test_stat_cards_only_link_to_modules_the_viewer_is_permitted_to_open(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($staff)->get('/dashboard');

        $response->assertOk();
        // Staff lacks ViewCompetencyGap - that card must render without a
        // link rather than pointing somewhere that would 403 if clicked.
        $response->assertDontSee('aria-label="Competency Gap"', false);
        // Staff does hold ViewCertificates and ViewDevelopmentPlans, so
        // those cards should still link.
        $response->assertSee('aria-label="Certificates Expiring"', false);
        $response->assertSee('aria-label="Development Plans"', false);
    }

    public function test_an_administrator_sees_links_on_every_stat_card(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('aria-label="Competency Gap"', false);
        $response->assertSee('aria-label="Development Plans"', false);
    }
}
