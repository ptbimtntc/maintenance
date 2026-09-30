<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsSpreadsheet;
use App\Concerns\PrunesNotificationLinks;
use App\Enums\PermissionName;
use App\Http\Requests\StoreOvertimeEntryRequest;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Models\User;
use App\Notifications\OvertimeEditApproved;
use App\Notifications\OvertimeEditRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OvertimeController extends Controller
{
    use ExportsSpreadsheet, PrunesNotificationLinks;

    /**
     * Logging overtime for your own team is a fact of being that team's
     * supervisor, not a grantable permission - every role can do it for
     * whoever reports to them, with no admin setup required.
     */
    private function supervisorEmployee(Request $request): ?Employee
    {
        return $request->user()->employee;
    }

    private function isSupervisor(Request $request): bool
    {
        $employee = $this->supervisorEmployee($request);

        return $employee !== null && $employee->directReports()->exists();
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->hasPermissionTo(PermissionName::ManageOvertime->value);
    }

    public function index(Request $request): View
    {
        $manage = $this->canManage($request);
        $supervisor = $this->supervisorEmployee($request);

        abort_unless($manage || ($supervisor && $supervisor->directReports()->exists()), 403);

        if ($manage) {
            $query = OvertimeEntry::query()->with(['employee.supervisor', 'createdBy']);

            if ($request->filled('supervisor_id')) {
                $query->whereHas('employee', fn ($q) => $q->where('supervisor_id', $request->integer('supervisor_id')));
            }

            if ($request->string('submission') == 'submitted') {
                $query->whereNotNull('submitted_to_hr_at');
            } elseif ($request->string('submission') == 'pending') {
                $query->whereNull('submitted_to_hr_at');
            }

            $entries = $query->orderByDesc('start_at')->get();

            $supervisors = Employee::query()->whereHas('directReports')->orderBy('full_name')->get();
        } else {
            $reportIds = $supervisor->directReports->pluck('id');

            $entries = OvertimeEntry::query()
                ->whereIn('employee_id', $reportIds)
                ->with(['employee', 'createdBy'])
                ->orderByDesc('start_at')
                ->get();

            $supervisors = collect();
        }

        $totalHours = $entries->sum->duration_hours;
        $byCompensation = $entries->groupBy('compensation_type')->map->sum('duration_hours');
        $pendingEditRequests = $entries->where('status', OvertimeEntry::STATUS_EDIT_REQUESTED)->count();

        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $trendLabels = $months->map(fn (Carbon $m) => $m->format('M Y'))->values()->all();
        $trendValues = $months->map(function (Carbon $month) use ($entries) {
            return round($entries
                ->filter(fn (OvertimeEntry $e) => $e->start_at->isSameMonth($month) && $e->start_at->isSameYear($month))
                ->sum->duration_hours, 2);
        })->values()->all();

        return view('overtime.index', [
            'manage' => $manage,
            'entries' => $entries,
            'supervisors' => $supervisors,
            'totalHours' => round($totalHours, 2),
            'totalEntries' => $entries->count(),
            'byCompensation' => $byCompensation,
            'pendingEditRequests' => $pendingEditRequests,
            'notSubmittedCount' => $entries->whereNull('submitted_to_hr_at')->count(),
            'trendLabels' => $trendLabels,
            'trendValues' => $trendValues,
            'filters' => $request->only(['supervisor_id', 'submission']),
        ]);
    }

    /**
     * Employees the current user is allowed to log/edit overtime for: their
     * own direct reports for an ordinary supervisor, or literally everyone
     * for a ManageOvertime holder (HR/Admin) - they aren't anyone's direct
     * supervisor in the org chart, but still need to be able to enter or
     * correct any team's overtime themselves.
     */
    private function selectableEmployees(Request $request): \Illuminate\Support\Collection
    {
        if ($this->canManage($request)) {
            return Employee::query()->orderBy('full_name')->get();
        }

        return $this->supervisorEmployee($request)?->directReports()->orderBy('full_name')->get() ?? collect();
    }

    public function create(Request $request): View
    {
        abort_unless($this->isSupervisor($request) || $this->canManage($request), 403, 'Only supervisors with direct reports can log overtime.');

        return view('overtime.form', [
            'entry' => null,
            'directReports' => $this->selectableEmployees($request),
        ]);
    }

    public function store(StoreOvertimeEntryRequest $request): RedirectResponse
    {
        abort_unless($this->isSupervisor($request) || $this->canManage($request), 403);

        if (! $this->canManage($request)) {
            $reportIds = $this->supervisorEmployee($request)->directReports()->pluck('id');
            abort_unless($reportIds->contains((int) $request->input('employee_id')), 403, 'You can only log overtime for your own team.');
        }

        OvertimeEntry::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'status' => OvertimeEntry::STATUS_LOCKED,
        ]);

        return redirect()->route('overtime.index')->with('status', 'Overtime entry logged.');
    }

    public function edit(Request $request, OvertimeEntry $overtimeEntry): View
    {
        if (! $this->canManage($request)) {
            $this->authorizeOwnEntry($request, $overtimeEntry);
            abort_unless($overtimeEntry->canBeEdited(), 403, 'This entry is locked. Request an edit first.');
        }

        return view('overtime.form', [
            'entry' => $overtimeEntry,
            'directReports' => $this->selectableEmployees($request),
        ]);
    }

    public function update(StoreOvertimeEntryRequest $request, OvertimeEntry $overtimeEntry): RedirectResponse
    {
        $manage = $this->canManage($request);

        if (! $manage) {
            $this->authorizeOwnEntry($request, $overtimeEntry);
            abort_unless($overtimeEntry->canBeEdited(), 403, 'This entry is locked. Request an edit first.');

            $reportIds = $this->supervisorEmployee($request)->directReports()->pluck('id');
            abort_unless($reportIds->contains((int) $request->input('employee_id')), 403);
        }

        $this->pruneNotificationsLinkedTo(route('overtime.edit', $overtimeEntry));

        $overtimeEntry->update([
            ...$request->validated(),
            // Editing again requires a fresh approval - a single edit spends the grant.
            // An HR/Admin edit is itself the correction, so it locks the entry
            // the same way rather than needing a request/approval round-trip.
            'status' => OvertimeEntry::STATUS_LOCKED,
            'edit_request_reason' => null,
            'edit_requested_at' => null,
            'edit_approved_by' => null,
            'edit_approved_at' => null,
        ]);

        return redirect()->route('overtime.index')->with('status', 'Overtime entry updated and re-locked.');
    }

    public function requestEdit(Request $request, OvertimeEntry $overtimeEntry): RedirectResponse
    {
        $this->authorizeOwnEntry($request, $overtimeEntry);
        abort_unless($overtimeEntry->isLocked(), 422, 'This entry already has an edit request in progress.');

        $data = $request->validate([
            'edit_request_reason' => ['required', 'string', 'max:1000'],
        ]);

        $overtimeEntry->update([
            'status' => OvertimeEntry::STATUS_EDIT_REQUESTED,
            'edit_request_reason' => $data['edit_request_reason'],
            'edit_requested_at' => now(),
        ]);

        User::permission(PermissionName::ManageOvertime->value)->get()
            ->each->notify(new OvertimeEditRequested($overtimeEntry));

        return redirect()->route('overtime.index')->with('status', 'Edit request submitted for approval.');
    }

    public function approveEdit(Request $request, OvertimeEntry $overtimeEntry): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        abort_unless($overtimeEntry->hasPendingEditRequest(), 422);

        $overtimeEntry->update([
            'status' => OvertimeEntry::STATUS_EDIT_APPROVED,
            'edit_approved_by' => $request->user()->id,
            'edit_approved_at' => now(),
        ]);

        $overtimeEntry->createdBy?->notify(new OvertimeEditApproved($overtimeEntry));

        return back()->with('status', 'Edit request approved.');
    }

    public function rejectEdit(Request $request, OvertimeEntry $overtimeEntry): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        abort_unless($overtimeEntry->hasPendingEditRequest(), 422);

        $overtimeEntry->update([
            'status' => OvertimeEntry::STATUS_LOCKED,
            'edit_request_reason' => null,
            'edit_requested_at' => null,
        ]);

        return back()->with('status', 'Edit request declined.');
    }

    private function authorizeOwnEntry(Request $request, OvertimeEntry $overtimeEntry): void
    {
        $supervisor = $this->supervisorEmployee($request);

        abort_unless($supervisor && $overtimeEntry->employee->supervisor_id === $supervisor->id, 403);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->canManage($request), 403);

        $ids = $request->input('overtime_entry_ids', []);

        $query = OvertimeEntry::query()->with(['employee.supervisor', 'createdBy']);

        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $query->notSubmitted();
        }

        $header = ['Supervisor', 'Employee', 'Employee Number', 'Start', 'End', 'Hours', 'Compensation', 'Remarks', 'Status', 'Submitted to HR'];

        $rows = $query->orderBy('start_at')->get()->map(fn (OvertimeEntry $e) => [
            $e->employee->supervisor?->full_name ?? $e->createdBy->name,
            $e->employee->full_name,
            $e->employee->employee_number,
            $e->start_at->format('Y-m-d H:i'),
            $e->end_at->format('Y-m-d H:i'),
            $e->duration_hours,
            OvertimeEntry::compensationLabel($e->compensation_type),
            $e->remarks,
            OvertimeEntry::statusLabel($e->status),
            $e->submitted_to_hr_at?->format('Y-m-d H:i') ?? '',
        ]);

        return $this->streamXlsx('overtime-export-'.now()->format('Y-m-d').'.xlsx', $header, $rows);
    }

    public function markSubmitted(Request $request): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);

        $ids = $request->input('overtime_entry_ids', []);

        $updated = OvertimeEntry::query()
            ->whereIn('id', is_array($ids) ? $ids : [])
            ->whereNull('submitted_to_hr_at')
            ->update(['submitted_to_hr_at' => now()]);

        return back()->with('status', "{$updated} entr".($updated === 1 ? 'y' : 'ies')." marked as submitted to HR.");
    }
}
