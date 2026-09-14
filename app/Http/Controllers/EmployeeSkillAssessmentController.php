<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\EmployeeSkillAssessment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeSkillAssessmentController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo(PermissionName::AssessCompetencies->value), 403);

        $data = $request->validate([
            'skill_id' => ['required', 'exists:skills,id'],
            'competency_level_id' => ['required', 'exists:competency_levels,id'],
            'assessment_date' => ['required', 'date', 'before_or_equal:today'],
            'assessment_method' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee->skillAssessments()->create([
            ...$data,
            'assessed_by' => $request->user()->id,
        ]);

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Skill assessment recorded.')
            ->with('activeTab', 'skills');
    }

    public function destroy(Request $request, Employee $employee, EmployeeSkillAssessment $assessment): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo(PermissionName::AssessCompetencies->value), 403);
        abort_unless($assessment->employee_id === $employee->id, 404);

        $assessment->delete();

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Assessment removed.')
            ->with('activeTab', 'skills');
    }
}
