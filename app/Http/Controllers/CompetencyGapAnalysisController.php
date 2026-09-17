<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CompetencyGapAnalysisController extends Controller
{
    /**
     * Aggregate the same per-employee gap rows used by the Skill Matrix
     * (Employee::skillGapRows()) into department-wide findings: which
     * skills, positions and areas need the most development attention.
     *
     * Training suggestions here are explicitly system-generated - they are
     * not approved training decisions, per the project brief.
     */
    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->visibleTo($request->user())
            ->with([
                'position.skillRequirements.skill',
                'position.skillRequirements.requiredCompetencyLevel',
                'skillAssessments.competencyLevel',
                'maintenanceArea',
                'maintenanceTeam',
            ])
            ->when($request->filled('maintenance_area_id'), fn ($q) => $q->where('maintenance_area_id', $request->integer('maintenance_area_id')))
            ->when($request->filled('maintenance_team_id'), fn ($q) => $q->where('maintenance_team_id', $request->integer('maintenance_team_id')))
            ->when($request->filled('position_id'), fn ($q) => $q->where('position_id', $request->integer('position_id')))
            ->get();

        $employeeSummaries = $employees->map(function (Employee $employee) {
            $rows = $employee->skillGapRows();

            return [
                'employee' => $employee,
                'rows' => $rows,
                'has_gap' => $rows->contains(fn ($row) => $row['status'] === 'gap'),
                'has_incomplete' => $rows->contains(fn ($row) => $row['status'] === 'incomplete'),
            ];
        });

        $skillBreakdown = $employeeSummaries
            ->flatMap(fn ($summary) => $summary['rows'])
            ->groupBy(fn ($row) => $row['skill']->id)
            ->map(function (Collection $rows) {
                $gapRows = $rows->where('status', 'gap');

                return [
                    'skill' => $rows->first()['skill'],
                    'employees_required' => $rows->count(),
                    'employees_with_gap' => $gapRows->count(),
                    'employees_incomplete' => $rows->where('status', 'incomplete')->count(),
                    'average_gap' => $gapRows->isEmpty() ? null : round($gapRows->avg('gap'), 1),
                ];
            })
            ->sortByDesc('employees_with_gap')
            ->values();

        $positionBreakdown = $employeeSummaries
            ->groupBy(fn ($summary) => $summary['employee']->position_id)
            ->filter(fn ($group, $key) => $key !== null)
            ->map(function (Collection $group) {
                $position = $group->first()['employee']->position;

                return [
                    'position' => $position,
                    'employee_count' => $group->count(),
                    'employees_with_gap' => $group->where('has_gap', true)->count(),
                ];
            })
            ->sortByDesc('employees_with_gap')
            ->values();

        $areaBreakdown = $employeeSummaries
            ->groupBy(fn ($summary) => $summary['employee']->maintenance_area_id)
            ->filter(fn ($group, $key) => $key !== null)
            ->map(function (Collection $group) {
                $area = $group->first()['employee']->maintenanceArea;

                return [
                    'area' => $area,
                    'employee_count' => $group->count(),
                    'employees_with_gap' => $group->where('has_gap', true)->count(),
                ];
            })
            ->sortByDesc('employees_with_gap')
            ->values();

        $trainingRecommendations = $skillBreakdown
            ->filter(fn ($row) => $row['employees_with_gap'] > 0)
            ->take(5);

        return view('competency-gap-analysis.index', [
            'summary' => [
                'employees_with_gaps' => $employeeSummaries->where('has_gap', true)->count(),
                'employees_with_incomplete_assessments' => $employeeSummaries->where('has_incomplete', true)->count(),
                'skills_needing_attention' => $skillBreakdown->where('employees_with_gap', '>', 0)->count(),
                'total_employees_evaluated' => $employeeSummaries->count(),
            ],
            'skillBreakdown' => $skillBreakdown,
            'positionBreakdown' => $positionBreakdown,
            'areaBreakdown' => $areaBreakdown,
            'trainingRecommendations' => $trainingRecommendations,
            'employeesWithGaps' => $employeeSummaries->where('has_gap', true)->values(),
            'maintenanceAreas' => MaintenanceArea::where('is_active', true)->orderBy('name')->get(),
            'maintenanceTeams' => MaintenanceTeam::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'filters' => $request->only(['maintenance_area_id', 'maintenance_team_id', 'position_id']),
        ]);
    }
}
