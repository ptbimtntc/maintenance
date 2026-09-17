<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSkillAssessment;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use App\Models\Skill;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the Maintenance Department dashboard.
     *
     * Only modules that currently have real data behind them are queried.
     * Modules planned for later phases (training, certificates) are shown
     * as an explicit "not yet available" state rather than fabricated
     * statistics.
     */
    public function index(): View
    {
        $orgSummary = [
            'departments' => Department::where('is_active', true)->count(),
            'maintenance_areas' => MaintenanceArea::where('is_active', true)->count(),
            'maintenance_teams' => MaintenanceTeam::where('is_active', true)->count(),
            'positions' => Position::where('is_active', true)->count(),
        ];

        $employeeTotal = Employee::count();
        $employeeActive = Employee::active()->count();

        $employeeSummary = [
            'total' => $employeeTotal,
            'active' => $employeeActive,
            'inactive' => $employeeTotal - $employeeActive,
        ];

        $competencyBreakdown = $this->competencyStatusBreakdown();

        $skillSummary = [
            'total_skills' => Skill::where('is_active', true)->count(),
            'average_competency_score' => $this->averageCurrentCompetencyScore(),
            'employees_with_gaps' => $competencyBreakdown['gap'],
            'breakdown' => $competencyBreakdown,
        ];

        $trainingSummary = [
            'programs' => TrainingProgram::where('status', 'active')->count(),
            'upcoming_sessions' => TrainingSession::whereIn('status', ['scheduled', 'ongoing'])->where('start_date', '>=', now())->count(),
            'completed_sessions' => TrainingSession::where('status', 'completed')->count(),
        ];

        $certificateSummary = $this->certificateSummary();

        return view('dashboard', [
            'orgSummary' => $orgSummary,
            'employeeSummary' => $employeeSummary,
            'skillSummary' => $skillSummary,
            'trainingSummary' => $trainingSummary,
            'certificateSummary' => $certificateSummary,
        ]);
    }

    /**
     * Average of each employee's most recent level per skill (by assessment
     * date, matching Employee::currentSkillAssessments()). Returns null (not
     * zero) when nothing has been assessed yet.
     */
    private function averageCurrentCompetencyScore(): ?float
    {
        $currentLevels = EmployeeSkillAssessment::query()
            ->with('competencyLevel')
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (EmployeeSkillAssessment $assessment) => $assessment->employee_id.'-'.$assessment->skill_id);

        if ($currentLevels->isEmpty()) {
            return null;
        }

        return round($currentLevels->avg(fn (EmployeeSkillAssessment $assessment) => $assessment->competencyLevel->level_number), 1);
    }

    private function certificateSummary(): array
    {
        $today = now()->startOfDay();
        $soonCutoff = $today->copy()->addDays(Certificate::expiringSoonDays());
        $verified = Certificate::where('verification_status', 'verified');

        return [
            'valid' => (clone $verified)->whereNull('expiry_date')->count()
                + (clone $verified)->whereDate('expiry_date', '>', $soonCutoff)->count(),
            'expiring_soon' => (clone $verified)->whereBetween('expiry_date', [$today, $soonCutoff])->count(),
            'expired' => (clone $verified)->whereDate('expiry_date', '<', $today)->count(),
            'pending_verification' => Certificate::where('verification_status', 'pending_verification')->count(),
        ];
    }

    /**
     * Classifies every employee into exactly one status, using the same
     * Employee::skillGapRows() definition of a gap as the Skill Matrix and
     * Competency Gap Analysis, so "what counts as a gap" never drifts:
     * - gap: at least one required skill below the required level.
     * - incomplete: no gap, but at least one required skill unassessed.
     * - meets: every required skill assessed at or above its level.
     * - no_requirements: the employee's position has no skill requirements.
     */
    private function competencyStatusBreakdown(): array
    {
        $employees = Employee::with([
            'position.skillRequirements.skill',
            'position.skillRequirements.requiredCompetencyLevel',
            'skillAssessments.competencyLevel',
        ])->get();

        $counts = ['meets' => 0, 'gap' => 0, 'incomplete' => 0, 'no_requirements' => 0];

        foreach ($employees as $employee) {
            $rows = $employee->skillGapRows();

            $status = match (true) {
                $rows->isEmpty() => 'no_requirements',
                $rows->contains(fn ($row) => $row['status'] === 'gap') => 'gap',
                $rows->contains(fn ($row) => $row['status'] === 'incomplete') => 'incomplete',
                default => 'meets',
            };

            $counts[$status]++;
        }

        return $counts;
    }
}
