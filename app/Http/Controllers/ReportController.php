<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsCsv;
use App\Concerns\ExportsSpreadsheet;
use App\Models\Employee;
use App\Models\EmployeeDevelopmentPlan;
use App\Models\EmployeeSkillAssessment;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ExportsCsv, ExportsSpreadsheet;

    /**
     * A landing page linking every report from the project brief. Reports
     * that are really just a filtered view of a module already built
     * (Skill Matrix, Certificates, Training Records, ...) link straight to
     * that module's own page - re-implementing the same list twice would
     * only let the two copies drift apart. Only the reports with no
     * existing equivalent get a dedicated page below.
     */
    public function index(): View
    {
        return view('reports.index');
    }

    /**
     * Total training hours per employee, with a completed-session count.
     */
    public function trainingHours(Request $request): View|StreamedResponse
    {
        $rows = Employee::query()
            ->withSum('trainingRecords', 'duration_hours')
            ->withCount(['trainingRecords as completed_trainings_count' => fn ($q) => $q->where('completion_status', 'completed')])
            ->get()
            ->filter(fn (Employee $e) => $e->training_records_sum_duration_hours > 0)
            ->sortByDesc('training_records_sum_duration_hours')
            ->values();

        if (in_array($request->string('export')->toString(), ['csv', 'xlsx'])) {
            $header = ['Employee', 'Employee Number', 'Total Hours', 'Completed Trainings'];
            $exportRows = $rows->map(fn (Employee $e) => [
                $e->full_name,
                $e->employee_number,
                $e->training_records_sum_duration_hours,
                $e->completed_trainings_count,
            ]);

            $basename = 'training-hours-by-employee-'.now()->format('Y-m-d');

            return $request->string('export') == 'xlsx'
                ? $this->streamXlsx("{$basename}.xlsx", $header, $exportRows)
                : $this->streamCsv("{$basename}.csv", $header, $exportRows);
        }

        $byDepartment = Employee::query()
            ->with('department')
            ->withSum('trainingRecords', 'duration_hours')
            ->get()
            ->groupBy(fn (Employee $e) => $e->department?->name ?? 'Unassigned')
            ->map(fn ($group) => $group->sum('training_records_sum_duration_hours'))
            ->filter(fn ($hours) => $hours > 0)
            ->sortDesc();

        return view('reports.training-hours', ['rows' => $rows, 'byDepartment' => $byDepartment]);
    }

    /**
     * Employee Development Summary: how many plans are in each status, and
     * a breakdown of the development actions being used.
     */
    public function developmentSummary(): View
    {
        $plans = EmployeeDevelopmentPlan::with('employee')->get();

        $byStatus = $plans->groupBy('status')->map->count();
        $byAction = $plans->groupBy('development_action')->map->count();
        $byPriority = $plans->groupBy('priority')->map->count();

        return view('reports.development-summary', [
            'totalPlans' => $plans->count(),
            'byStatus' => $byStatus,
            'byAction' => $byAction,
            'byPriority' => $byPriority,
            'overdue' => $plans->filter(fn ($p) => $p->status !== 'completed' && $p->target_completion_date?->isPast())->count(),
        ]);
    }

    /**
     * Competency Assessment History: every assessment ever recorded,
     * across all employees - the append-only log described in Phase 3,
     * surfaced here as a report rather than only per-employee.
     */
    public function assessmentHistory(Request $request): View|StreamedResponse
    {
        $query = EmployeeSkillAssessment::query()
            ->with(['employee', 'skill', 'competencyLevel', 'assessedBy'])
            ->when($request->filled('employee_search'), fn ($q) => $q->whereHas(
                'employee',
                fn ($eq) => $eq->search($request->string('employee_search')->toString())
            ));

        if (in_array($request->string('export')->toString(), ['csv', 'xlsx'])) {
            $header = ['Employee', 'Skill', 'Level', 'Assessment Date', 'Assessed By', 'Method'];
            $rows = $query->orderByDesc('assessment_date')->get()->map(fn (EmployeeSkillAssessment $a) => [
                $a->employee->full_name,
                $a->skill->name,
                $a->competencyLevel->level_number.' - '.$a->competencyLevel->name,
                $a->assessment_date->format('Y-m-d'),
                $a->assessedBy?->name,
                $a->assessment_method,
            ]);

            $basename = 'competency-assessment-history-'.now()->format('Y-m-d');

            return $request->string('export') == 'xlsx'
                ? $this->streamXlsx("{$basename}.xlsx", $header, $rows)
                : $this->streamCsv("{$basename}.csv", $header, $rows);
        }

        $assessments = $query->orderByDesc('assessment_date')->paginate(30)->withQueryString();

        return view('reports.assessment-history', [
            'assessments' => $assessments,
            'filters' => $request->only(['employee_search']),
        ]);
    }
}
