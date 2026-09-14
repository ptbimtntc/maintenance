<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Http\Requests\StoreDevelopmentPlanRequest;
use App\Models\CompetencyLevel;
use App\Models\Employee;
use App\Models\EmployeeDevelopmentPlan;
use App\Models\Skill;
use App\Models\TrainingProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeDevelopmentPlanController extends Controller
{
    public function index(Request $request): View
    {
        $plans = EmployeeDevelopmentPlan::query()
            ->with('employee')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('development-plans.index', [
            'plans' => $plans,
            'statuses' => EmployeeDevelopmentPlan::STATUSES,
            'priorities' => EmployeeDevelopmentPlan::PRIORITIES,
            'filters' => $request->only(['status', 'priority']),
        ]);
    }

    public function create(Employee $employee): View
    {
        $this->authorize(PermissionName::ManageDevelopmentPlans->value);

        return view('development-plans.form', [
            'employee' => $employee,
            'plan' => null,
            ...$this->formOptions($employee),
        ]);
    }

    public function store(StoreDevelopmentPlanRequest $request, Employee $employee): RedirectResponse
    {
        $employee->developmentPlans()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Development plan created.')
            ->with('activeTab', 'development');
    }

    public function edit(Employee $employee, EmployeeDevelopmentPlan $plan): View
    {
        $this->authorize(PermissionName::ManageDevelopmentPlans->value);
        abort_unless($plan->employee_id === $employee->id, 404);

        return view('development-plans.form', [
            'employee' => $employee,
            'plan' => $plan,
            ...$this->formOptions($employee),
        ]);
    }

    public function update(StoreDevelopmentPlanRequest $request, Employee $employee, EmployeeDevelopmentPlan $plan): RedirectResponse
    {
        abort_unless($plan->employee_id === $employee->id, 404);

        $plan->update($request->validated());

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Development plan updated.')
            ->with('activeTab', 'development');
    }

    public function destroy(Employee $employee, EmployeeDevelopmentPlan $plan): RedirectResponse
    {
        $this->authorize(PermissionName::ManageDevelopmentPlans->value);
        abort_unless($plan->employee_id === $employee->id, 404);

        $plan->delete();

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Development plan removed.')
            ->with('activeTab', 'development');
    }

    private function formOptions(Employee $employee): array
    {
        return [
            'skills' => Skill::where('is_active', true)->orderBy('name')->get(),
            'competencyLevels' => CompetencyLevel::where('is_active', true)->orderBy('level_number')->get(),
            'trainingPrograms' => TrainingProgram::where('status', 'active')->orderBy('title')->get(),
            'possibleMentors' => Employee::where('id', '!=', $employee->id)->orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
            'actions' => EmployeeDevelopmentPlan::ACTIONS,
            'priorities' => EmployeeDevelopmentPlan::PRIORITIES,
            'statuses' => EmployeeDevelopmentPlan::STATUSES,
        ];
    }
}
