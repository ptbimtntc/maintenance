<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\CompetencyLevel;
use App\Models\Employee;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSkillAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_supervisor_can_record_a_skill_assessment_for_a_direct_report(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisorEmployee = Employee::factory()->create(['user_id' => $supervisor->id]);

        $employee = Employee::factory()->create(['supervisor_id' => $supervisorEmployee->id]);
        $skill = Skill::factory()->create();
        $level = CompetencyLevel::factory()->create();

        $response = $this->actingAs($supervisor)->post(route('employees.skill-assessments.store', $employee), [
            'skill_id' => $skill->id,
            'competency_level_id' => $level->id,
            'assessment_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('employees.show', $employee));
        $this->assertDatabaseHas('employee_skill_assessments', [
            'employee_id' => $employee->id,
            'skill_id' => $skill->id,
            'assessed_by' => $supervisor->id,
        ]);
    }

    public function test_maintenance_staff_cannot_record_skill_assessments(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $employee = Employee::factory()->create(['user_id' => $staff->id]);
        $skill = Skill::factory()->create();
        $level = CompetencyLevel::factory()->create();

        $response = $this->actingAs($staff)->post(route('employees.skill-assessments.store', $employee), [
            'skill_id' => $skill->id,
            'competency_level_id' => $level->id,
            'assessment_date' => now()->format('Y-m-d'),
        ]);

        $response->assertForbidden();
    }

    public function test_a_new_assessment_becomes_the_employees_current_level_without_erasing_history(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $managerEmployee = Employee::factory()->create(['user_id' => $manager->id]);

        $employee = Employee::factory()->create(['supervisor_id' => $managerEmployee->id]);
        $skill = Skill::factory()->create();
        $oldLevel = CompetencyLevel::factory()->create(['level_number' => 1]);
        $newLevel = CompetencyLevel::factory()->create(['level_number' => 3]);

        $employee->skillAssessments()->create([
            'skill_id' => $skill->id,
            'competency_level_id' => $oldLevel->id,
            'assessment_date' => now()->subYear(),
        ]);

        $this->actingAs($manager)->post(route('employees.skill-assessments.store', $employee), [
            'skill_id' => $skill->id,
            'competency_level_id' => $newLevel->id,
            'assessment_date' => now()->format('Y-m-d'),
        ]);

        $this->assertSame(2, $employee->skillAssessments()->count());
        $current = $employee->currentSkillAssessments()->get($skill->id);
        $this->assertEquals($newLevel->id, $current->competency_level_id);
    }
}
