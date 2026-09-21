<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmploymentStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A visual org chart built from the same supervisor_id hierarchy used for
 * visibility scoping (Employee::scopeVisibleTo()). Deliberately reuses that
 * scope rather than a separate "show everyone" query: a Supervisor's chart
 * shows exactly the same people they can already see elsewhere (themselves
 * plus their direct reports), while Administrator/People Development see
 * the whole company since they hold ViewAllEmployees.
 */
class OrganizationChartController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->visibleTo($request->user())
            ->whereHas('employmentStatus', fn ($q) => $q->where('code', 'ACTIVE'))
            ->with(['skillPosition', 'position'])
            ->orderBy('full_name')
            ->get();

        $visibleIds = $employees->pluck('id')->flip();

        // Roots are visible employees whose supervisor isn't also in the
        // visible set - for an org-wide viewer that's the top of the
        // company; for someone scoped to themselves + direct reports,
        // their own record naturally has no visible supervisor, so they
        // become the root of their own (shallow) chart. Filtering to Active
        // employees above means a non-active supervisor's chain is excluded
        // too - their active reports simply surface as roots instead.
        $roots = $employees->filter(
            fn (Employee $employee) => ! $employee->supervisor_id || ! $visibleIds->has($employee->supervisor_id)
        )->values();

        $childrenByParent = $employees->groupBy('supervisor_id');

        return view('organization-chart.index', [
            'roots' => $roots,
            'childrenByParent' => $childrenByParent,
        ]);
    }
}
