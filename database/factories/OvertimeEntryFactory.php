<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeEntry>
 */
class OvertimeEntryFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'employee_id' => Employee::factory(),
            'created_by' => User::factory(),
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+3 hours'),
            'remarks' => fake()->sentence(),
            'compensation_type' => fake()->randomElement(OvertimeEntry::COMPENSATION_TYPES),
            'status' => OvertimeEntry::STATUS_LOCKED,
        ];
    }
}
