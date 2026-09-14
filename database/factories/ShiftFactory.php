<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Shift',
            'code' => fake()->unique()->regexify('SHIFT-[A-Z]'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
