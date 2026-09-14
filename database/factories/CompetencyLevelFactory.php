<?php

namespace Database\Factories;

use App\Models\CompetencyLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetencyLevel>
 */
class CompetencyLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'level_number' => fake()->unique()->numberBetween(0, 200),
            'code' => fake()->unique()->regexify('L[0-9]{3}'),
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
