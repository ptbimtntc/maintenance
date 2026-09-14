<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSkillAssessment;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use App\Models\Skill;
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

        $employeeSummary = [
            'total' => Employee::count(),
            'active' => Employee::active()->count(),
        ];

        $skillSummary = [
            'total_skills' => Skill::where('is_active', true)->count(),
            'average_competency_score' => $this->averageCurrentCompetencyScore(),
            'employees_with_gaps' => $this->countEmployeesWithCompetencyGaps(),
        ];

        $pendingModules = [
            'Training Programs',
            'Upcoming Training Sessions',
            'Certificates Expiring Soon',
        ];

        return view('dashboard', [
            'orgSummary' => $orgSummary,
            'employeeSummary' => $employeeSummary,
            'skillSummary' => $skillSummary,
            'pendingModules' => $pendingModules,
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

    /**
     * Number of employees with at least one required skill below the
     * required level, using the same Employee::skillGapRows() definition of
     * a gap as the Skill Matrix and Competency Gap Analysis. Employees with
     * no requirements defined, or fully unassessed, are not counted as
     * "gapped" - they are simply not yet measurable.
     */
    private function countEmployeesWithCompetencyGaps(): int
    {
        $employees = Employee::with([
            'position.skillRequirements.skill',
            'position.skillRequirements.requiredCompetencyLevel',
            'skillAssessments.competencyLevel',
        ])->get();

        return $employees->filter(
            fn (Employee $employee) => $employee->skillGapRows()->contains(fn ($row) => $row['status'] === 'gap')
        )->count();
    }
}
