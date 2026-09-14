<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\MaintenanceArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceArea>
 */
class MaintenanceAreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->unique()->words(2, true).' Area',
            'code' => fake()->unique()->regexify('AREA-[A-Z]{3}'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
