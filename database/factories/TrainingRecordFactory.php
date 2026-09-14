<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\TrainingRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingRecord>
 */
class TrainingRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'training_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'duration_hours' => fake()->randomElement([2, 4, 8]),
            'attendance_status' => 'attended',
            'completion_status' => 'completed',
            'record_date' => now(),
        ];
    }
}
