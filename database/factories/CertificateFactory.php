<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'name' => fake()->words(3, true).' Certificate',
            'issuing_organization' => fake()->company(),
            'issue_date' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'verification_status' => 'verified',
        ];
    }
}
