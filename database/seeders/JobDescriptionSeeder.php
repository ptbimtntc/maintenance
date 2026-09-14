<?php

namespace Database\Seeders;

use App\Models\JobDescription;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class JobDescriptionSeeder extends Seeder
{
    /**
     * One sample, active job description so the feature has real data to
     * show on the Employee profile and Job Descriptions list.
     */
    public function run(): void
    {
        $position = Position::where('code', 'POS-TECH')->first();
        $supervisorPosition = Position::where('code', 'POS-SPV')->first();
        $admin = User::where('email', 'admin@mpd.test')->first();

        if (! $position || ! $admin) {
            return;
        }

        JobDescription::firstOrCreate(
            ['position_id' => $position->id, 'version' => 1],
            [
                'reports_to_position_id' => $supervisorPosition?->id,
                'job_title' => 'Maintenance Technician',
                'job_purpose' => 'Perform routine and corrective maintenance on production machinery to minimise downtime and maintain safe, reliable operation.',
                'main_responsibilities' => "Carry out preventive maintenance per schedule.\nRespond to breakdown calls and perform corrective repairs.\nRecord maintenance activities in the CMMS.\nFollow lockout-tagout and safety procedures at all times.",
                'detailed_duties' => "Inspect mechanical equipment for wear and abnormal conditions.\nReplace worn parts (bearings, belts, seals) as needed.\nAssist senior technicians on complex repairs.\nMaintain a clean and organised work area.",
                'required_education' => 'SMK (Vocational High School) or equivalent',
                'required_experience' => '0-2 years in an industrial maintenance environment',
                'required_technical_skills' => 'Basic mechanical maintenance, hand and power tool use, basic troubleshooting',
                'required_soft_skills' => 'Teamwork, attention to detail, willingness to learn',
                'required_certifications' => 'None required; K3 (workplace safety) orientation provided on hire',
                'safety_responsibilities' => 'Wear required PPE at all times. Follow lockout-tagout procedures. Report unsafe conditions immediately.',
                'direct_reports_summary' => 'None',
                'version' => 1,
                'effective_date' => now()->subMonths(6),
                'review_date' => now()->addMonths(6),
                'status' => 'active',
                'approved_by' => $admin->id,
                'approval_date' => now()->subMonths(6),
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );
    }
}
