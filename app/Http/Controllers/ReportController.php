<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsSpreadsheet;
use App\Enums\PermissionName;
use App\Models\CompetencyLevel;
use App\Models\Employee;
use App\Models\EmployeeDevelopmentPlan;
use App\Models\EmployeeSkillAssessment;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ExportsSpreadsheet;

    /**
     * Column headers the Assessment History "Import XLSX" upload is
     * expected to have, in this order. Every row always creates a new
     * assessment - the assessment log is append-only by design (see
     * EmployeeSkillAssessmentController::store()), so import never updates
     * or overwrites a prior assessment either.
     */
    private const ASSESSMENT_IMPORT_COLUMNS = [
        'Employee Number', 'Skill', 'Level', 'Assessment Date', 'Method',
    ];

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

        if ($request->string('export') == 'xlsx') {
            $header = ['Employee', 'Employee Number', 'Total Hours', 'Completed Trainings'];
            $exportRows = $rows->map(fn (Employee $e) => [
                $e->full_name,
                $e->employee_number,
                $e->training_records_sum_duration_hours,
                $e->completed_trainings_count,
            ]);

            return $this->streamXlsx('training-hours-by-employee-'.now()->format('Y-m-d').'.xlsx', $header, $exportRows);
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

        if ($request->string('export') == 'xlsx') {
            $header = ['Employee', 'Skill', 'Level', 'Assessment Date', 'Assessed By', 'Method'];
            $rows = $query->orderByDesc('assessment_date')->get()->map(fn (EmployeeSkillAssessment $a) => [
                $a->employee->full_name,
                $a->skill->name,
                $a->competencyLevel->level_number.' - '.$a->competencyLevel->name,
                $a->assessment_date->format('Y-m-d'),
                $a->assessedBy?->name,
                $a->assessment_method,
            ]);

            return $this->streamXlsx('competency-assessment-history-'.now()->format('Y-m-d').'.xlsx', $header, $rows);
        }

        $assessments = $query->orderByDesc('assessment_date')->paginate(30)->withQueryString();

        return view('reports.assessment-history', [
            'assessments' => $assessments,
            'filters' => $request->only(['employee_search']),
        ]);
    }

    /**
     * Bulk-records skill assessments from an uploaded .xlsx file shaped
     * like ASSESSMENT_IMPORT_COLUMNS. The assessment log is append-only
     * (see EmployeeSkillAssessmentController::store()), so - like the
     * Certificates and Training Records imports - every row always adds a
     * new assessment rather than updating an existing one.
     */
    public function assessmentHistoryImport(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo(PermissionName::AssessCompetencies->value), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $rows = (new XlsxReader)->load($request->file('file')->getRealPath())
            ->getActiveSheet()
            ->toArray(null, true, true, false);

        $header = array_map(fn ($cell) => trim((string) $cell), array_shift($rows) ?? []);

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = array_combine($header, array_pad($row, count($header), null));

            $employeeNumber = trim((string) ($data['Employee Number'] ?? ''));
            $skillName = trim((string) ($data['Skill'] ?? ''));

            if ($employeeNumber === '' && $skillName === '') {
                continue;
            }

            if ($employeeNumber === '') {
                $errors[] = "Row {$rowNumber}: Employee Number is required.";

                continue;
            }

            $employee = Employee::query()->visibleTo($request->user())->where('employee_number', $employeeNumber)->first();

            if (! $employee) {
                $errors[] = "Row {$rowNumber}: employee \"{$employeeNumber}\" not found.";

                continue;
            }

            if ($skillName === '') {
                $errors[] = "Row {$rowNumber}: Skill is required.";

                continue;
            }

            $skill = Skill::where('name', $skillName)->first();

            if (! $skill) {
                $errors[] = "Row {$rowNumber}: Skill \"{$skillName}\" not found.";

                continue;
            }

            // Accepts either the export's "N - Name" format or a bare level number.
            $levelValue = trim((string) ($data['Level'] ?? ''));
            $levelNumber = (int) Str::before($levelValue, '-');
            $competencyLevel = $levelNumber > 0 ? CompetencyLevel::where('level_number', $levelNumber)->first() : null;

            if (! $competencyLevel) {
                $errors[] = "Row {$rowNumber}: Level \"{$levelValue}\" not recognized - row skipped.";

                continue;
            }

            $dateValue = trim((string) ($data['Assessment Date'] ?? ''));

            if ($dateValue === '') {
                $errors[] = "Row {$rowNumber}: Assessment Date is required.";

                continue;
            }

            try {
                $assessmentDate = Carbon::parse($dateValue);
            } catch (\Throwable) {
                $errors[] = "Row {$rowNumber}: Assessment Date \"{$dateValue}\" is not a valid date.";

                continue;
            }

            if ($assessmentDate->isAfter(now())) {
                $errors[] = "Row {$rowNumber}: Assessment Date \"{$dateValue}\" is in the future - row skipped.";

                continue;
            }

            $employee->skillAssessments()->create([
                'skill_id' => $skill->id,
                'competency_level_id' => $competencyLevel->id,
                'assessment_date' => $assessmentDate->format('Y-m-d'),
                'assessment_method' => trim((string) ($data['Method'] ?? '')) ?: null,
                'assessed_by' => $request->user()->id,
            ]);

            $created++;
        }

        $redirect = redirect()->route('reports.assessment-history')
            ->with('status', "{$created} assessment(s) recorded from import.");

        if ($errors !== []) {
            $shown = array_slice($errors, 0, 8);
            $suffix = count($errors) > 8 ? ' …and '.(count($errors) - 8).' more.' : '';
            $redirect->with('import_errors', count($errors).' issue(s) found: '.implode(' | ', $shown).$suffix);
        }

        return $redirect;
    }
}
