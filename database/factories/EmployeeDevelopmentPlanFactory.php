<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeDevelopmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDevelopmentPlan>
 */
class EmployeeDevelopmentPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'development_objective' => fake()->sentence(6),
            'development_action' => fake()->randomElement(array_keys(EmployeeDevelopmentPlan::ACTIONS)),
            'priority' => fake()->randomElement(EmployeeDevelopmentPlan::PRIORITIES),
            'status' => 'not_started',
            'progress_percentage' => 0,
        ];
    }
}
