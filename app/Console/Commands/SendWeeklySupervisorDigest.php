<?php

namespace App\Console\Commands;

use App\Mail\WeeklySupervisorDigest;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\TrainingSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklySupervisorDigest extends Command
{
    protected $signature = 'app:send-weekly-supervisor-digest';

    protected $description = 'Email every supervisor a weekly summary of their direct reports\' certificate expirations, upcoming training, and open skill gaps.';

    public function handle(): int
    {
        $supervisors = Employee::query()
            ->whereHas('directReports')
            ->with(['user', 'directReports.position.skillRequirements.skill', 'directReports.position.skillRequirements.requiredCompetencyLevel', 'directReports.skillAssessments.competencyLevel'])
            ->get();

        $sent = 0;
        $skippedNoAccount = 0;

        foreach ($supervisors as $supervisor) {
            if (! $supervisor->user?->email) {
                $skippedNoAccount++;

                continue;
            }

            $reportIds = $supervisor->directReports->pluck('id');

            $expiringCertificates = Certificate::query()
                ->whereIn('employee_id', $reportIds)
                ->where('verification_status', 'verified')
                ->whereNotNull('expiry_date')
                ->with('employee')
                ->get()
                ->filter(fn (Certificate $c) => $c->status() === 'expiring_soon')
                ->values();

            $expiredCertificates = Certificate::query()
                ->whereIn('employee_id', $reportIds)
                ->where('verification_status', 'verified')
                ->whereNotNull('expiry_date')
                ->with('employee')
                ->get()
                ->filter(fn (Certificate $c) => $c->status() === 'expired')
                ->values();

            $upcomingSessions = TrainingSession::query()
                ->whereIn('status', ['scheduled', 'ongoing'])
                ->whereBetween('start_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                ->whereHas('participants', fn ($q) => $q->whereIn('employee_id', $reportIds))
                ->with('trainingProgram')
                ->orderBy('start_date')
                ->get();

            $employeesWithSkillGaps = $supervisor->directReports
                ->filter(fn (Employee $employee) => $employee->skillGapRows()->contains(fn ($row) => $row['status'] === 'gap'))
                ->values();

            if ($expiringCertificates->isEmpty() && $expiredCertificates->isEmpty() && $upcomingSessions->isEmpty() && $employeesWithSkillGaps->isEmpty()) {
                continue;
            }

            Mail::to($supervisor->user->email)->send(new WeeklySupervisorDigest(
                $supervisor,
                $expiringCertificates,
                $expiredCertificates,
                $upcomingSessions,
                $employeesWithSkillGaps,
            ));

            $sent++;
        }

        $this->info("Sent {$sent} weekly digest(s). Skipped {$skippedNoAccount} supervisor(s) with no linked user account.");

        return self::SUCCESS;
    }
}
