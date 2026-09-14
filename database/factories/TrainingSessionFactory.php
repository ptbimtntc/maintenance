<?php

namespace Database\Factories;

use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', '+2 months');

        return [
            'training_program_id' => TrainingProgram::factory(),
            'start_date' => $start,
            'end_date' => $start,
            'status' => 'scheduled',
        ];
    }
}
