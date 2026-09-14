<?php

namespace Database\Seeders;

use App\Models\CompetencyLevel;
use App\Models\Employee;
use App\Models\EmployeeDevelopmentPlan;
use App\Models\Skill;
use App\Models\TrainingCategory;
use App\Models\TrainingProgram;
use App\Models\TrainingProvider;
use App\Models\TrainingRecord;
use App\Models\TrainingSession;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrainingSeeder extends Seeder
{
    /**
     * Sample training master data, one program with a past (completed) and
     * an upcoming session, a training record, and a development plan tied
     * to the Mechanical Maintenance gap seeded in SkillsAndCompetencySeeder
     * so the story is consistent across modules.
     */
    public function run(): void
    {
        $categories = [
            'TC-TECH' => 'Technical Skills',
            'TC-SAFETY' => 'Safety',
            'TC-SOFT' => 'Soft Skills',
        ];
        $categoryModels = [];
        foreach ($categories as $code => $name) {
            $categoryModels[$code] = TrainingCategory::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $types = ['TT-CLASS' => 'Classroom', 'TT-OJT' => 'On-the-Job Training', 'TT-ELEARN' => 'E-learning'];
        $typeModels = [];
        foreach ($types as $code => $name) {
            $typeModels[$code] = TrainingType::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $provider = TrainingProvider::firstOrCreate(
            ['code' => 'PRV-CERT'],
            ['name' => 'National Certification Institute (sample provider)']
        );

        $mechSkill = Skill::where('code', 'SK-MECH-MAINT')->first();

        $program = TrainingProgram::firstOrCreate(
            ['code' => 'TRN-MECH01'],
            [
                'title' => 'Mechanical Maintenance Fundamentals',
                'training_category_id' => $categoryModels['TC-TECH']->id,
                'training_type_id' => $typeModels['TT-CLASS']->id,
                'description' => 'Foundational course covering routine mechanical maintenance tasks for production machinery.',
                'objectives' => 'Raise technicians from Basic Awareness to Basic Working Knowledge on core mechanical maintenance tasks.',
                'target_audience' => 'Maintenance Technicians',
                'is_internal' => true,
                'trainer_name' => 'Made Manager',
                'location_id' => null,
                'duration_value' => 16,
                'duration_unit' => 'hours',
                'status' => 'active',
            ]
        );

        if ($mechSkill) {
            $program->skills()->syncWithoutDetaching([$mechSkill->id]);
        }

        $pastSession = TrainingSession::firstOrCreate(
            ['training_program_id' => $program->id, 'start_date' => now()->subMonths(2)->startOfMonth()],
            [
                'session_title' => 'Batch 1',
                'end_date' => now()->subMonths(2)->startOfMonth()->addDay(),
                'status' => 'completed',
                'max_participants' => 15,
            ]
        );

        $upcomingSession = TrainingSession::firstOrCreate(
            ['training_program_id' => $program->id, 'start_date' => now()->addMonth()->startOfMonth()],
            [
                'session_title' => 'Batch 2',
                'end_date' => now()->addMonth()->startOfMonth()->addDay(),
                'status' => 'scheduled',
                'max_participants' => 15,
            ]
        );

        $staffUser = User::where('email', 'staff@mpd.test')->first();
        $staffEmployee = $staffUser ? Employee::where('user_id', $staffUser->id)->first() : null;
        $managerUser = User::where('email', 'manager@mpd.test')->first();

        if ($staffEmployee) {
            $upcomingSession->participants()->firstOrCreate(
                ['employee_id' => $staffEmployee->id],
                ['attendance_status' => 'confirmed']
            );

            TrainingRecord::firstOrCreate(
                ['employee_id' => $staffEmployee->id, 'training_program_id' => $program->id, 'training_session_id' => $pastSession->id],
                [
                    'training_date' => $pastSession->start_date,
                    'training_type_id' => $typeModels['TT-CLASS']->id,
                    'trainer_name' => 'Made Manager',
                    'duration_hours' => 16,
                    'attendance_status' => 'attended',
                    'completion_status' => 'completed',
                    'assessment_score' => 72.5,
                    'assessment_result' => 'pass',
                    'recorded_by' => $managerUser?->id,
                    'record_date' => $pastSession->start_date,
                ]
            );

            $levelOne = CompetencyLevel::where('level_number', 1)->first();
            $levelTwo = CompetencyLevel::where('level_number', 2)->first();

            if ($mechSkill && $levelOne && $levelTwo) {
                EmployeeDevelopmentPlan::firstOrCreate(
                    ['employee_id' => $staffEmployee->id, 'related_skill_id' => $mechSkill->id],
                    [
                        'development_objective' => 'Close the Mechanical Maintenance gap for the Maintenance Technician role.',
                        'current_competency_level_id' => $levelOne->id,
                        'target_competency_level_id' => $levelTwo->id,
                        'development_action' => 'formal_training',
                        'recommended_training_program_id' => $program->id,
                        'target_completion_date' => now()->addMonths(2),
                        'priority' => 'high',
                        'status' => 'in_progress',
                        'progress_percentage' => 40,
                        'manager_remarks' => 'Enrolled in Batch 2 of the Mechanical Maintenance Fundamentals course.',
                        'created_by' => $managerUser?->id,
                    ]
                );
            }
        }
    }
}
