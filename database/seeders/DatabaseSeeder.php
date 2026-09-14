<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            MasterDataSeeder::class,
            DemoUsersSeeder::class,
            EmployeeSeeder::class,
            SkillsAndCompetencySeeder::class,
            JobDescriptionSeeder::class,
            TrainingSeeder::class,
        ]);
    }
}
