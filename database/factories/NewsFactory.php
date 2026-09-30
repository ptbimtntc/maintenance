<?php

namespace Database\Factories;

use App\Models\News;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'template' => 'standard',
            'is_published' => true,
            'created_by' => User::factory(),
        ];
    }
}
