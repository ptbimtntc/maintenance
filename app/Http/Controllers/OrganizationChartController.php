<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Employee;
use Illuminate\Http\Request;
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
 */
class OrganizationChartController extends Controller
{
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

        if (! $user->hasPermissionTo(PermissionName::ViewAllEmployees->value)) {
            $employees = $employees->concat($this->ancestorChain($user->employee))->unique('id');
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
        $current = $employee?->supervisor;
        $hops = 0;

        while ($current !== null && $hops < 20) {
            $ancestors->push($current);
            $current = $current->supervisor;
            $hops++;
        }

        return $ancestors;
    }
}
