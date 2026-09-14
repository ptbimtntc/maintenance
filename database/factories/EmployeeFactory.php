<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\Location;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'preferred_name' => null,
            'gender' => fake()->randomElement(['Male', 'Female']),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-20 years'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'maintenance_area_id' => MaintenanceArea::factory(),
            'maintenance_team_id' => MaintenanceTeam::factory(),
            'position_id' => Position::factory(),
            'employment_type_id' => EmploymentType::factory(),
            'employment_status_id' => EmploymentStatus::factory(),
            'location_id' => Location::factory(),
            'shift_id' => Shift::factory(),
            'date_joined' => fake()->dateTimeBetween('-15 years', '-1 months'),
            'education' => fake()->randomElement(['SMK', 'D3', 'S1']),
            'technical_background' => fake()->randomElement(['Mechanical', 'Electrical', 'Instrumentation', 'General']),
            'years_of_experience' => fake()->numberBetween(0, 25),
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }

    /**
     * Attach this employee to an existing login account, matching name/email.
     */
    public function forUser(\App\Models\User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
