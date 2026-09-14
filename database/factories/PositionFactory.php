<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'title' => fake()->unique()->jobTitle(),
            'code' => fake()->unique()->regexify('POS-[A-Z]{4}'),
            'grade_level' => fake()->numberBetween(1, 5),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
