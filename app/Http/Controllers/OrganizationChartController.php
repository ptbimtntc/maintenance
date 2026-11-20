<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersByMultipleIds;
use App\Enums\PermissionName;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Scopes\ExcludePlantManagerScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A visual org chart built from the same supervisor_id hierarchy used for
 * visibility scoping (Employee::scopeVisibleTo()). Deliberately reuses that
 * scope rather than a separate "show everyone" query: a Supervisor's chart
 * shows exactly the same people they can already see elsewhere (themselves
 * plus their direct reports), while Administrator/People Development see
 * the whole company since they hold ViewAllEmployees.
 *
 * For everyone else, the scoped set alone would show them as a root with no
 * one above them, which reads as "I'm at the top of the company" - wrong,
 * and unhelpful for placing themselves in it. So their upward supervisor
 * chain (to the top) is added on top of the scoped set, without pulling in
 * any of their supervisors' other reports (siblings/unrelated branches).
 *
 * The Plant Manager is hidden from every other employee screen (see
 * ExcludePlantManagerScope) but is the head of this chart, so it opts back
 * in here: added for org-wide viewers, and reached through the ancestor
 * chain for everyone else.
 */
class OrganizationChartController extends Controller
{
    use FiltersByMultipleIds;

    /** Scope shown on a first visit; matched case-insensitively by name. */
    private const DEFAULT_BUSINESS_UNITS = ['Tire Cord', 'Half Product'];

    private const DEFAULT_DEPARTMENTS = ['Maintenance'];

    private const DEFAULT_EMPLOYMENT_TYPES = ['Contract', 'Permanent'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $user = $request->user();

        $employees = Employee::query()
            ->visibleTo($user)
            ->whereHas('employmentStatus', fn ($q) => $q->where('code', 'ACTIVE'))
            ->with(['skillPosition', 'position'])
            ->orderBy('full_name')
            ->get();

        if ($user->hasPermissionTo(PermissionName::ViewAllEmployees->value)) {
            $plantManager = Employee::plantManager()?->load(['skillPosition', 'position']);

            if ($plantManager) {
                $employees = $employees->prepend($plantManager);
            }
        } else {
            $employees = $employees->concat($this->ancestorChain($user->employee))->unique('id');
        }

        // Mechanic/electric headcount of each supervisor's Blue Collar direct
        // reports, taken before any search/filter narrows the tree so the
        // numbers describe the real team rather than just the matches.
        $teamTrades = $employees
            ->where('workforce_category', 'BC')
            ->groupBy('supervisor_id')
            ->map(fn (Collection $team) => [
                'M' => $team->filter(fn (Employee $employee) => $employee->technicalTrade() === 'M')->count(),
                'E' => $team->filter(fn (Employee $employee) => $employee->technicalTrade() === 'E')->count(),
            ]);

        $businessUnits = BusinessUnit::where('is_active', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        $employmentTypes = EmploymentType::where('is_active', true)->orderBy('name')->get();

        // The form always posts `applied`, so an explicit "all" (nothing
        // ticked) can be told apart from a first visit, which gets the
        // default scope instead. Reset returns to that default.
        $applied = $request->boolean('applied');
        $businessUnitIds = $applied
            ? $this->idList($request->input('business_unit_id'))
            : $this->defaultIds($businessUnits, self::DEFAULT_BUSINESS_UNITS);
        $departmentIds = $applied
            ? $this->idList($request->input('department_id'))
            : $this->defaultIds($departments, self::DEFAULT_DEPARTMENTS);
        $employmentTypeIds = $applied
            ? $this->idList($request->input('employment_type_id'))
            : $this->defaultIds($employmentTypes, self::DEFAULT_EMPLOYMENT_TYPES);

        $search = trim((string) $request->input('search', ''));
        $filters = [
            'search' => $search,
            'business_unit_id' => $businessUnitIds,
            'department_id' => $departmentIds,
            'employment_type_id' => $employmentTypeIds,
        ];
        $filtered = filled($search) || $businessUnitIds !== [] || $departmentIds !== []
            || $employmentTypeIds !== [];
        $highlightIds = collect();
        $legendSource = $employees;

        if ($filtered) {
            $matches = $employees
                ->when(filled($search), fn (Collection $collection) => $collection->filter(
                    fn (Employee $employee) => str_contains(strtolower($employee->full_name), strtolower($search))
                        || str_contains(strtolower((string) $employee->employee_number), strtolower($search))
                ))
                ->when($businessUnitIds !== [], fn (Collection $collection) => $collection->whereIn('business_unit_id', $businessUnitIds))
                ->when($departmentIds !== [], fn (Collection $collection) => $collection->whereIn('department_id', $departmentIds))
                ->when($employmentTypeIds !== [], fn (Collection $collection) => $collection->whereIn('employment_type_id', $employmentTypeIds));

            // Only a name/NIK search singles people out; a business unit or
            // department scope matches whole groups, where highlighting
            // everyone would just be noise.
            $highlightIds = filled($search) ? $matches->pluck('id') : collect();
            $legendSource = $matches;

            // Narrow the tree down to just the matched employee(s) plus
            // their reporting line up to the top, instead of the whole
            // company - "where does this person sit" rather than "show
            // everyone".
            $employees = $matches
                ->concat($matches->flatMap(fn (Employee $employee) => $this->ancestorChain($employee)))
                ->unique('id')
                ->values();
        }

        $visibleIds = $employees->pluck('id')->flip();

        // Roots are visible employees whose supervisor isn't also in the
        // visible set - for an org-wide viewer that's the top of the
        // company; for someone scoped to themselves + direct reports (plus
        // their ancestor chain above), the topmost ancestor has no visible
        // supervisor and becomes the root instead.
        $roots = $employees->filter(
            fn (Employee $employee) => ! $employee->supervisor_id || ! $visibleIds->has($employee->supervisor_id)
        )->values();

        $childrenByParent = $employees->groupBy('supervisor_id');

        return view('organization-chart.index', [
            'roots' => $roots,
            'childrenByParent' => $childrenByParent,
            'filters' => $filters,
            'filtered' => $filtered,
            'highlightIds' => $highlightIds,
            'teamTrades' => $teamTrades,
            'workforceCounts' => $legendSource->countBy('workforce_category'),
            'searching' => filled($search),
            'matchCount' => $filtered ? $legendSource->count() : null,
            'businessUnits' => $businessUnits,
            'departments' => $departments,
            'employmentTypes' => $employmentTypes,
        ]);
    }

    /**
     * Walks supervisor_id upward from the given employee (exclusive) to the
     * top, e.g. [my supervisor, their supervisor, ...]. Not filtered by
     * active status - a chain shouldn't break just because one ancestor's
     * employment status happens to be inactive. Capped at 20 hops as a
     * guard against a circular supervisor_id.
     */
    private function ancestorChain(?Employee $employee): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $current = $this->supervisorOf($employee);
        $hops = 0;

        while ($current !== null && $hops < 20) {
            $ancestors->push($current);
            $current = $this->supervisorOf($current);
            $hops++;
        }

        return $ancestors;
    }

    /**
     * The employee's supervisor, Plant Manager included (the plain
     * supervisor relation can't see them, as it goes through the global scope).
     */
    private function supervisorOf(?Employee $employee): ?Employee
    {
        if (! $employee?->supervisor_id) {
            return null;
        }

        return Employee::withoutGlobalScope(ExcludePlantManagerScope::class)
            ->with(['skillPosition', 'position'])
            ->find($employee->supervisor_id);
    }

    /**
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $options
     * @param  list<string>  $names
     * @return list<int>
     */
    private function defaultIds(Collection $options, array $names): array
    {
        $names = array_map('strtolower', $names);

        return $options
            ->filter(fn ($option) => in_array(strtolower($option->name), $names, true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
