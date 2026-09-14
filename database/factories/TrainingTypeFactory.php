<?php

namespace Database\Factories;

use App\Models\TrainingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingType>
 */
class TrainingTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'code' => fake()->unique()->regexify('TT-[A-Z]{4}'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
