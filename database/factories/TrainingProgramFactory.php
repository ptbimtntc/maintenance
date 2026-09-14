<?php

namespace Database\Factories;

use App\Models\TrainingProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingProgram>
 */
class TrainingProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(3, true).' Training',
            'code' => fake()->unique()->regexify('TRN-[A-Z0-9]{5}'),
            'description' => fake()->sentence(),
            'objectives' => fake()->sentence(),
            'target_audience' => 'Maintenance Technicians',
            'is_internal' => true,
            'duration_value' => fake()->randomElement([4, 8, 16, 24]),
            'duration_unit' => 'hours',
            'status' => 'active',
        ];
    }
}
