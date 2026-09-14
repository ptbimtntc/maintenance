<?php

namespace Database\Factories;

use App\Models\JobDescription;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobDescription>
 */
class JobDescriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'job_title' => fake()->jobTitle(),
            'job_purpose' => fake()->sentence(),
            'version' => 1,
            'status' => 'draft',
        ];
    }
}
