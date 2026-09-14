<?php

namespace Database\Factories;

use App\Models\CompetencyLevel;
use App\Models\Employee;
use App\Models\EmployeeSkillAssessment;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSkillAssessment>
 */
class EmployeeSkillAssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'skill_id' => Skill::factory(),
            'competency_level_id' => CompetencyLevel::factory(),
            'assessment_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'assessment_method' => fake()->randomElement(['Practical Assessment', 'Observation', 'Written Test']),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
