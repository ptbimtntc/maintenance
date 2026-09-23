<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\EmployeeDevelopmentPlan;
use App\Models\EmploymentSource;
use App\Models\EmploymentType;
use App\Models\TrainingRecord;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the Maintenance Department dashboard. Every number here comes
     * from a real query against current data - there is no historical
     * "status as of last month" snapshot table, so cards that would need
     * one for a trend/delta simply don't show one, rather than fabricating
     * a percentage. Where a genuine month-by-month series exists (things
     * derived from a date column, like when training happened or a plan
     * was created), a real sparkline is shown instead.
     */
    public function index(Request $request): View
    {
        $year = $request->integer('year') ?: (int) now()->year;
        $businessUnitId = $request->integer('business_unit_id') ?: null;
        $employmentTypeId = $request->integer('employment_type_id') ?: null;
        $employmentSourceId = $request->integer('employment_source_id') ?: null;

        $employeeScope = fn () => Employee::query()
            ->when($businessUnitId, fn (Builder $q) => $q->where('business_unit_id', $businessUnitId))
            ->when($employmentTypeId, fn (Builder $q) => $q->where('employment_type_id', $employmentTypeId))
            ->when($employmentSourceId, fn (Builder $q) => $q->where('employment_source_id', $employmentSourceId));

        $employeeIds = $employeeScope()->pluck('id');

        $employeeTotal = $employeeIds->count();
        $employeeActive = $employeeScope()->active()->count();

        $competencyBreakdown = $this->competencyStatusBreakdown($employeeIds);
        $certificateSummary = $this->certificateSummary($employeeIds, $year);
        $developmentPlanSummary = $this->developmentPlanSummary($employeeIds);
        $trainingHoursThisYear = $this->trainingHours($employeeIds, $year);

        return view('dashboard', [
            'filters' => [
                'year' => $year,
                'business_unit_id' => $businessUnitId,
                'employment_type_id' => $employmentTypeId,
                'employment_source_id' => $employmentSourceId,
            ],
            'filterOptions' => [
                'years' => $this->availableYears(),
                'businessUnits' => BusinessUnit::where('is_active', true)->orderBy('name')->get(),
                'employmentTypes' => EmploymentType::where('is_active', true)->orderBy('name')->get(),
                'employmentSources' => EmploymentSource::where('is_active', true)->orderBy('name')->get(),
            ],
            'kpis' => [
                'total_employees' => $employeeTotal,
                'active_employees' => $employeeActive,
                'competency_gap' => $competencyBreakdown['gap'],
                'training_hours' => $trainingHoursThisYear,
                'certificates_expiring' => $certificateSummary['expiring_soon'],
                'development_plans' => $developmentPlanSummary['total'],
            ],
            'employeeTrend' => $this->monthlyCumulativeHeadcount($employeeIds, $year),
            'trainingHoursTrend' => $this->monthlyTrainingHours($employeeIds, $year),
            'developmentPlansTrend' => $this->monthlyDevelopmentPlansCreated($employeeIds, $year),
            'competencyBreakdown' => $competencyBreakdown,
            'topSkillGaps' => $this->topSkillGaps($employeeIds),
            'trainingSeries' => $this->trainingSeries($employeeIds, $year),
            'upcomingSessions' => $this->upcomingSessions($employeeIds),
            'certificateSummary' => $certificateSummary,
            'developmentPriorities' => $this->developmentPriorities($employeeIds),
            'workforceMix' => $this->workforceMix($employeeScope),
            'maintenanceAreaDistribution' => $this->maintenanceAreaDistribution($employeeScope),
            'insights' => [
                'employees_needing_development' => $competencyBreakdown['gap'],
                'employees_needing_development_pct' => $employeeTotal > 0 ? round($competencyBreakdown['gap'] / $employeeTotal * 100) : 0,
                'certificates_needing_renewal' => $certificateSummary['expiring_soon'],
                'certificates_needing_renewal_pct' => $certificateSummary['total'] > 0 ? round($certificateSummary['expiring_soon'] / $certificateSummary['total'] * 100) : 0,
                'assessments_incomplete' => $competencyBreakdown['incomplete'],
                'assessments_incomplete_pct' => $employeeTotal > 0 ? round($competencyBreakdown['incomplete'] / $employeeTotal * 100) : 0,
                'critical_skill_gaps' => $this->topSkillGaps($employeeIds)->where('count', '>=', 3)->count(),
            ],
        ]);
    }

    /** Every year that shows up in training records, certificates, or development plans, newest first. */
    private function availableYears(): array
    {
        $years = collect([now()->year])
            ->merge(TrainingRecord::query()->selectRaw('DISTINCT strftime("%Y", training_date) as y')->pluck('y'))
            ->merge(Certificate::query()->selectRaw('DISTINCT strftime("%Y", issue_date) as y')->pluck('y'))
            ->filter()
            ->map(fn ($y) => (int) $y)
            ->unique()
            ->sortDesc()
            ->values();

        return $years->all();
    }

    private function trainingHours($employeeIds, int $year): float
    {
        return (float) TrainingRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereYear('training_date', $year)
            ->sum('duration_hours');
    }

    /** Cumulative headcount by month (employees already joined by that month), for a real "growth" sparkline. */
    private function monthlyCumulativeHeadcount($employeeIds, int $year): array
    {
        $joinDates = Employee::query()->whereIn('id', $employeeIds)->whereNotNull('date_joined')->pluck('date_joined');

        return collect(range(1, 12))->map(function ($month) use ($joinDates, $year) {
            $cutoff = Carbon::create($year, $month, 1)->endOfMonth();

            return $joinDates->filter(fn ($date) => $date->lte($cutoff))->count();
        })->all();
    }

    private function monthlyTrainingHours($employeeIds, int $year): array
    {
        $rows = TrainingRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereYear('training_date', $year)
            ->selectRaw('strftime("%m", training_date) as m, SUM(duration_hours) as hours')
            ->groupBy('m')
            ->pluck('hours', 'm');

        return collect(range(1, 12))->map(fn ($m) => (float) ($rows[str_pad($m, 2, '0', STR_PAD_LEFT)] ?? 0))->all();
    }

    private function monthlyDevelopmentPlansCreated($employeeIds, int $year): array
    {
        $rows = EmployeeDevelopmentPlan::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereYear('created_at', $year)
            ->selectRaw('strftime("%m", created_at) as m, COUNT(*) as total')
            ->groupBy('m')
            ->pluck('total', 'm');

        return collect(range(1, 12))->map(fn ($m) => (int) ($rows[str_pad($m, 2, '0', STR_PAD_LEFT)] ?? 0))->all();
    }

    private function certificateSummary($employeeIds, int $year): array
    {
        $today = now()->startOfDay();
        $soonCutoff = $today->copy()->addDays(Certificate::expiringSoonDays());
        $base = fn () => Certificate::query()->whereIn('employee_id', $employeeIds);

        $verified = fn () => (clone $base())->where('verification_status', 'verified');

        $summary = [
            'valid' => (clone $verified())->whereNull('expiry_date')->count()
                + (clone $verified())->whereDate('expiry_date', '>', $soonCutoff)->count(),
            'expiring_soon' => (clone $verified())->whereBetween('expiry_date', [$today, $soonCutoff])->count(),
            'expired' => (clone $verified())->whereDate('expiry_date', '<', $today)->count(),
            'pending_verification' => (clone $base())->where('verification_status', 'pending_verification')->count(),
        ];

        $summary['total'] = array_sum($summary);

        return $summary;
    }

    private function developmentPlanSummary($employeeIds): array
    {
        $base = EmployeeDevelopmentPlan::query()->whereIn('employee_id', $employeeIds);

        return [
            'total' => (clone $base)->count(),
            'in_progress' => (clone $base)->whereIn('status', ['not_started', 'in_progress'])->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];
    }

    /**
     * How many employees are in a skill "gap" status (current level below
     * what their position requires), grouped by skill - the same
     * definition Employee::skillGapRows() uses everywhere else.
     */
    private function topSkillGaps($employeeIds): \Illuminate\Support\Collection
    {
        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->with([
                'position.skillRequirements.skill',
                'position.skillRequirements.requiredCompetencyLevel',
                'skillAssessments.competencyLevel',
            ])
            ->get();

        $counts = [];

        foreach ($employees as $employee) {
            foreach ($employee->skillGapRows() as $row) {
                if ($row['status'] !== 'gap') {
                    continue;
                }

                $name = $row['skill']->name;
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        arsort($counts);

        return collect($counts)->take(6)->map(fn ($count, $name) => ['skill' => $name, 'count' => $count])->values();
    }

    /** Training hours delivered and distinct participants per month, for the combo chart. */
    private function trainingSeries($employeeIds, int $year): array
    {
        $rows = TrainingRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereYear('training_date', $year)
            ->selectRaw('strftime("%m", training_date) as m, SUM(duration_hours) as hours, COUNT(DISTINCT employee_id) as participants')
            ->groupBy('m')
            ->get()
            ->keyBy('m');

        return [
            'labels' => collect(range(1, 12))->map(fn ($m) => Carbon::create($year, $m, 1)->format('M'))->all(),
            'hours' => collect(range(1, 12))->map(fn ($m) => (float) ($rows[str_pad($m, 2, '0', STR_PAD_LEFT)]->hours ?? 0))->all(),
            'participants' => collect(range(1, 12))->map(fn ($m) => (int) ($rows[str_pad($m, 2, '0', STR_PAD_LEFT)]->participants ?? 0))->all(),
        ];
    }

    private function upcomingSessions($employeeIds): \Illuminate\Support\Collection
    {
        return TrainingSession::query()
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->where('start_date', '>=', now()->startOfDay())
            ->whereHas('participants', fn (Builder $q) => $q->whereIn('employee_id', $employeeIds))
            ->with(['trainingProgram.trainingType', 'participants' => fn ($q) => $q->whereIn('employee_id', $employeeIds)])
            ->orderBy('start_date')
            ->take(3)
            ->get();
    }

    private function developmentPriorities($employeeIds): \Illuminate\Support\Collection
    {
        return EmployeeDevelopmentPlan::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', ['not_started', 'in_progress'])
            ->with(['employee', 'relatedSkill', 'currentCompetencyLevel', 'targetCompetencyLevel'])
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->take(5)
            ->get();
    }

    private function workforceMix(\Closure $employeeScope): array
    {
        $counts = (clone $employeeScope())->selectRaw('workforce_category, COUNT(*) as total')
            ->groupBy('workforce_category')
            ->pluck('total', 'workforce_category');

        return [
            'BC' => (int) ($counts['BC'] ?? 0),
            'WCM' => (int) ($counts['WCM'] ?? 0),
        ];
    }

    private function maintenanceAreaDistribution(\Closure $employeeScope): \Illuminate\Support\Collection
    {
        return (clone $employeeScope())
            ->join('maintenance_areas', 'maintenance_areas.id', '=', 'employees.maintenance_area_id')
            ->selectRaw('maintenance_areas.name as area, COUNT(*) as total')
            ->groupBy('maintenance_areas.name')
            ->orderByDesc('total')
            ->take(6)
            ->get()
            ->map(fn ($row) => ['area' => $row->area, 'total' => (int) $row->total]);
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
    private function competencyStatusBreakdown($employeeIds): array
    {
        $employees = Employee::whereIn('id', $employeeIds)->with([
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
