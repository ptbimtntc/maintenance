<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->unique()->words(2, true).' Division',
            'code' => fake()->unique()->regexify('[A-Z]{3}-[A-Z]{3}'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
