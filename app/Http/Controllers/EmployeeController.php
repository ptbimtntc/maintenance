<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsCsv;
use App\Concerns\ExportsSpreadsheet;
use App\Enums\PermissionName;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\CompetencyLevel;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\Location;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use App\Models\Shift;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    use ExportsCsv, ExportsSpreadsheet;

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Employee::class);

        $user = $request->user();

        $query = Employee::query()->with([
            'department', 'maintenanceArea', 'maintenanceTeam', 'position', 'employmentStatus',
        ]);

        $query->visibleTo($user);

        $query->search($request->string('search')->toString())
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('maintenance_area_id'), fn ($q) => $q->where('maintenance_area_id', $request->integer('maintenance_area_id')))
            ->when($request->filled('maintenance_team_id'), fn ($q) => $q->where('maintenance_team_id', $request->integer('maintenance_team_id')))
            ->when($request->filled('position_id'), fn ($q) => $q->where('position_id', $request->integer('position_id')))
            ->when($request->filled('employment_status_id'), fn ($q) => $q->where('employment_status_id', $request->integer('employment_status_id')));

        if (in_array($request->string('export')->toString(), ['csv', 'xlsx'])) {
            $header = ['Employee Number', 'Full Name', 'Position', 'Department', 'Maintenance Area', 'Maintenance Team', 'Employment Status'];
            $rows = $query->orderBy('full_name')->get()->map(fn (Employee $e) => [
                $e->employee_number,
                $e->full_name,
                $e->position?->title,
                $e->department?->name,
                $e->maintenanceArea?->name,
                $e->maintenanceTeam?->name,
                $e->employmentStatus?->name,
            ]);

            $basename = 'employees-'.now()->format('Y-m-d');

            return $request->string('export') == 'xlsx'
                ? $this->streamXlsx("{$basename}.xlsx", $header, $rows)
                : $this->streamCsv("{$basename}.csv", $header, $rows);
        }

        $employees = $query->orderBy('full_name')->paginate(15)->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'maintenanceAreas' => MaintenanceArea::where('is_active', true)->orderBy('name')->get(),
            'maintenanceTeams' => MaintenanceTeam::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'employmentStatuses' => EmploymentStatus::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only([
                'search', 'department_id', 'maintenance_area_id', 'maintenance_team_id', 'position_id', 'employment_status_id',
            ]),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Employee::class);

        return view('employees.create', $this->formOptions());
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('employees.show', $employee)->with('status', 'Employee created.');
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);

        $employee->load([
            'department', 'division', 'maintenanceArea', 'maintenanceTeam', 'position',
            'employmentType', 'employmentStatus', 'location', 'shift', 'supervisor', 'manager',
            'position.skillRequirements.skill', 'position.skillRequirements.requiredCompetencyLevel',
            'skillAssessments' => fn ($q) => $q->with(['skill', 'competencyLevel', 'assessedBy'])->orderByDesc('assessment_date')->orderByDesc('id'),
            'trainingRecords' => fn ($q) => $q->with('trainingProgram')->orderByDesc('training_date'),
            'developmentPlans' => fn ($q) => $q->with(['relatedSkill', 'currentCompetencyLevel', 'targetCompetencyLevel', 'mentor'])->orderByDesc('created_at'),
            'certificates' => fn ($q) => $q->with('certificateType')->orderByDesc('created_at'),
        ]);

        return view('employees.show', [
            'employee' => $employee,
            'currentSkillLevels' => $employee->skillAssessments->unique('skill_id')->keyBy('skill_id'),
            'skills' => Skill::where('is_active', true)->orderBy('name')->get(),
            'competencyLevels' => CompetencyLevel::where('is_active', true)->orderBy('level_number')->get(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('employees.edit', [
            'employee' => $employee,
            ...$this->formOptions($employee),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('employees.show', $employee)->with('status', 'Employee updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return redirect()->route('employees.index')->with('status', 'Employee removed.');
    }

    /**
     * Shared dropdown option lists for the create/edit forms.
     */
    private function formOptions(?Employee $employee = null): array
    {
        return [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'divisions' => Division::where('is_active', true)->orderBy('name')->get(),
            'maintenanceAreas' => MaintenanceArea::where('is_active', true)->orderBy('name')->get(),
            'maintenanceTeams' => MaintenanceTeam::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'employmentTypes' => EmploymentType::where('is_active', true)->orderBy('name')->get(),
            'employmentStatuses' => EmploymentStatus::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'shifts' => Shift::where('is_active', true)->orderBy('name')->get(),
            'possibleSupervisors' => Employee::query()
                ->when($employee, fn ($q) => $q->where('id', '!=', $employee->id))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number']),
        ];
    }
}
