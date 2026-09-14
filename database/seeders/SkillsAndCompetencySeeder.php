<?php

namespace Database\Seeders;

use App\Models\CompetencyLevel;
use App\Models\Employee;
use App\Models\EmployeeSkillAssessment;
use App\Models\Position;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class SkillsAndCompetencySeeder extends Seeder
{
    /**
     * Sample skill categories, a configurable competency scale, a skill
     * catalog, position requirements, and a handful of assessments so the
     * Skill Matrix has real (if fictional) data to display.
     */
    public function run(): void
    {
        $categories = [
            ['code' => 'CAT-MECH', 'name' => 'Mechanical'],
            ['code' => 'CAT-ELEC', 'name' => 'Electrical & Instrumentation'],
            ['code' => 'CAT-SAFETY', 'name' => 'Safety'],
            ['code' => 'CAT-SOFT', 'name' => 'Soft Skills'],
        ];

        $categoryModels = [];
        foreach ($categories as $category) {
            $categoryModels[$category['code']] = SkillCategory::firstOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name']]
            );
        }

        $levels = [
            ['level_number' => 0, 'code' => 'L0', 'name' => 'Not Assessed', 'color' => '#9ca3af'],
            ['level_number' => 1, 'code' => 'L1', 'name' => 'Basic Awareness', 'color' => '#f97316'],
            ['level_number' => 2, 'code' => 'L2', 'name' => 'Basic Working Knowledge', 'color' => '#eab308'],
            ['level_number' => 3, 'code' => 'L3', 'name' => 'Competent / Independent', 'color' => '#22c55e'],
            ['level_number' => 4, 'code' => 'L4', 'name' => 'Advanced / Expert', 'color' => '#3b82f6'],
            ['level_number' => 5, 'code' => 'L5', 'name' => 'Trainer / Subject Matter Expert', 'color' => '#8b5cf6'],
        ];

        $levelModels = [];
        foreach ($levels as $level) {
            $levelModels[$level['level_number']] = CompetencyLevel::firstOrCreate(
                ['level_number' => $level['level_number']],
                ['code' => $level['code'], 'name' => $level['name'], 'color' => $level['color']]
            );
        }

        $skills = [
            ['code' => 'SK-MECH-MAINT', 'name' => 'Mechanical Maintenance', 'category' => 'CAT-MECH', 'type' => 'technical'],
            ['code' => 'SK-HYDRAULIC', 'name' => 'Hydraulic Systems', 'category' => 'CAT-MECH', 'type' => 'technical'],
            ['code' => 'SK-PNEUMATIC', 'name' => 'Pneumatic Systems', 'category' => 'CAT-MECH', 'type' => 'technical'],
            ['code' => 'SK-WELDING', 'name' => 'Welding', 'category' => 'CAT-MECH', 'type' => 'technical'],
            ['code' => 'SK-ELEC-MAINT', 'name' => 'Electrical Maintenance', 'category' => 'CAT-ELEC', 'type' => 'technical'],
            ['code' => 'SK-PLC', 'name' => 'PLC Troubleshooting', 'category' => 'CAT-ELEC', 'type' => 'technical'],
            ['code' => 'SK-INSTRUMENT', 'name' => 'Instrumentation', 'category' => 'CAT-ELEC', 'type' => 'technical'],
            ['code' => 'SK-SAFETY-IND', 'name' => 'Industrial Safety', 'category' => 'CAT-SAFETY', 'type' => 'technical'],
            ['code' => 'SK-SAFETY-ELEC', 'name' => 'Electrical Safety', 'category' => 'CAT-SAFETY', 'type' => 'technical'],
            ['code' => 'SK-RCA', 'name' => 'Root Cause Analysis', 'category' => 'CAT-SOFT', 'type' => 'soft'],
            ['code' => 'SK-PROBLEM', 'name' => 'Problem Solving', 'category' => 'CAT-SOFT', 'type' => 'soft'],
            ['code' => 'SK-TEAMWORK', 'name' => 'Teamwork', 'category' => 'CAT-SOFT', 'type' => 'soft'],
        ];

        $skillModels = [];
        foreach ($skills as $skill) {
            $skillModels[$skill['code']] = Skill::firstOrCreate(
                ['code' => $skill['code']],
                [
                    'name' => $skill['name'],
                    'skill_category_id' => $categoryModels[$skill['category']]->id,
                    'skill_type' => $skill['type'],
                ]
            );
        }

        // Required skills per position (a representative subset, not exhaustive).
        $requirements = [
            'POS-TECH' => [
                ['skill' => 'SK-MECH-MAINT', 'level' => 2],
                ['skill' => 'SK-SAFETY-IND', 'level' => 2],
            ],
            'POS-STECH' => [
                ['skill' => 'SK-MECH-MAINT', 'level' => 3],
                ['skill' => 'SK-HYDRAULIC', 'level' => 2],
                ['skill' => 'SK-SAFETY-IND', 'level' => 3],
            ],
            'POS-ENG' => [
                ['skill' => 'SK-PLC', 'level' => 3],
                ['skill' => 'SK-INSTRUMENT', 'level' => 3],
                ['skill' => 'SK-RCA', 'level' => 3],
            ],
            'POS-SPV' => [
                ['skill' => 'SK-MECH-MAINT', 'level' => 3],
                ['skill' => 'SK-SAFETY-IND', 'level' => 4],
                ['skill' => 'SK-PROBLEM', 'level' => 3],
            ],
            'POS-MGR' => [
                ['skill' => 'SK-MECH-MAINT', 'level' => 3],
                ['skill' => 'SK-SAFETY-IND', 'level' => 4],
                ['skill' => 'SK-PROBLEM', 'level' => 4],
            ],
        ];

        foreach ($requirements as $positionCode => $skillRequirements) {
            $position = Position::where('code', $positionCode)->first();

            if (! $position) {
                continue;
            }

            foreach ($skillRequirements as $req) {
                $position->skillRequirements()->firstOrCreate(
                    ['skill_id' => $skillModels[$req['skill']]->id],
                    ['required_competency_level_id' => $levelModels[$req['level']]->id]
                );
            }
        }

        // Sample assessments for the demo employees linked to login accounts,
        // deliberately mixing "meets", "gap" and "unassessed" outcomes.
        $assessor = User::where('email', 'manager@mpd.test')->first();

        $sampleAssessments = [
            'staff@mpd.test' => [
                ['skill' => 'SK-MECH-MAINT', 'level' => 1], // below the technician requirement of 2
            ],
            'supervisor@mpd.test' => [
                ['skill' => 'SK-MECH-MAINT', 'level' => 3],
                ['skill' => 'SK-SAFETY-IND', 'level' => 4],
                // SK-PROBLEM intentionally left unassessed
            ],
            'manager@mpd.test' => [
                ['skill' => 'SK-MECH-MAINT', 'level' => 4],
                ['skill' => 'SK-SAFETY-IND', 'level' => 4],
                ['skill' => 'SK-PROBLEM', 'level' => 4],
            ],
        ];

        foreach ($sampleAssessments as $email => $entries) {
            $employee = Employee::whereHas('user', fn ($q) => $q->where('email', $email))->first();

            if (! $employee) {
                continue;
            }

            foreach ($entries as $entry) {
                EmployeeSkillAssessment::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'skill_id' => $skillModels[$entry['skill']]->id,
                    ],
                    [
                        'competency_level_id' => $levelModels[$entry['level']]->id,
                        'assessed_by' => $assessor?->id,
                        'assessment_date' => now()->subMonths(1),
                        'assessment_method' => 'Practical Assessment',
                    ]
                );
            }
        }
    }
}
