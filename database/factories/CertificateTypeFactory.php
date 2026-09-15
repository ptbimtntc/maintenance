<?php

namespace Database\Factories;

use App\Models\CertificateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateType>
 */
class CertificateTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Certification',
            'code' => fake()->unique()->regexify('CT-[A-Z]{4}'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
