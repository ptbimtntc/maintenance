<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\Position;
use App\Models\TrainingParticipant;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Console\Command;

class SetupCertificateSimulation extends Command
{
    protected $signature = 'app:setup-certificate-simulation';

    protected $description = 'Create one demo person (NIK SIM001) with a training scheduled for today and a quiz, to walk through the certificate flow end to end.';

    public function handle(): int
    {
        $program = TrainingProgram::firstOrCreate(['code' => 'BMM'], [
            'title' => 'Basic Mechanical Maintenance',
            'description' => 'Simulation program for the certificate flow.',
            'status' => 'active',
            'is_internal' => true,
            'trainer_name' => 'Budi Santoso',
            'validity_months' => 24,
            'passing_score' => 70,
            'authorizer_name' => 'Andi Wijaya',
            'authorizer_title' => 'Maintenance Manager',
        ]);

        if ($program->questions()->doesntExist()) {
            $bank = [
                ['Which tool is used to measure the diameter of a shaft accurately?', ['Steel ruler', 'Vernier caliper', 'Tape measure', 'Spirit level'], ['B'], false],
                ['What is the first step before starting maintenance on a machine?', ['Remove the guard', 'Apply Lock Out Tag Out (LOTO)', 'Start the machine', 'Clean the area'], ['B'], false],
                ['Which are common causes of bearing failure? (choose all that apply)', ['Lack of lubrication', 'Contamination', 'Correct alignment', 'Overloading'], ['A', 'B', 'D'], true],
                ['A torque wrench is used to...', ['Cut metal', 'Tighten fasteners to a specified torque', 'Measure voltage', 'Align pulleys'], ['B'], false],
                ['Which PPE is mandatory when grinding?', ['Safety glasses', 'Sandals', 'Loose gloves', 'None'], ['A'], false],
            ];

            foreach ($bank as [$text, $options, $correct, $multiple]) {
                $question = $program->questions()->create(['question_text' => $text, 'allow_multiple_answers' => $multiple]);

                foreach (['A', 'B', 'C', 'D'] as $i => $label) {
                    $question->choices()->create(['option_label' => $label, 'choice_text' => $options[$i], 'is_correct' => in_array($label, $correct, true), 'points' => 1]);
                }
            }
        }

        $employee = Employee::withTrashed()->firstOrNew(['employee_number' => 'SIM001']);
        $employee->fill([
            'full_name' => 'SIMULASI KARYAWAN',
            'employment_status_id' => EmploymentStatus::where('code', 'ACTIVE')->value('id'),
            'position_id' => Position::where('title', 'Maintenance Technician')->value('id'),
            'workforce_category' => 'BC',
        ])->save();
        $employee->restore();

        if (! $employee->user_id) {
            $user = new User(['name' => $employee->full_name, 'email' => 'sim001@employees.mpd.local', 'password' => 'SIM001', 'must_change_password' => false]);
            $user->username = 'sim001';
            $user->email_verified_at = now();
            $user->save();
            $user->assignRole(RoleName::MaintenanceStaff->value);
            $employee->forceFill(['user_id' => $user->id])->save();
        }

        $session = TrainingSession::firstOrCreate(
            ['training_program_id' => $program->id, 'session_title' => 'Simulation session'],
            ['start_date' => today(), 'end_date' => today(), 'status' => 'scheduled', 'trainer_name' => 'Budi Santoso', 'max_participants' => 20]
        );
        $session->update(['start_date' => today(), 'end_date' => today()]);

        TrainingParticipant::firstOrCreate(
            ['training_session_id' => $session->id, 'employee_id' => $employee->id],
            ['attendance_status' => 'invited']
        );

        $this->info('Simulation ready: login "SIM001" / password "SIM001". Training today; attendance is still "invited".');

        return self::SUCCESS;
    }
}
