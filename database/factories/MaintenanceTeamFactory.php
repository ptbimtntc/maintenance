<?php

namespace Database\Factories;

use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceTeam>
 */
class MaintenanceTeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'maintenance_area_id' => MaintenanceArea::factory(),
            'name' => fake()->unique()->words(2, true).' Team',
            'code' => fake()->unique()->regexify('TEAM-[A-Z]{4}'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
