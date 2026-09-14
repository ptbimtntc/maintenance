<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SkillMatrixController extends Controller
{
    /**
     * Build an employee x skill competency matrix.
     *
     * Gap = required competency level - current competency level, per the
     * business rule in the project brief. A skill the employee has never
     * been assessed on is shown explicitly as "Not assessed" rather than
     * silently treated as meeting the requirement.
     */
    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->with(['position.skillRequirements.requiredCompetencyLevel', 'maintenanceArea', 'maintenanceTeam'])
            ->when($request->filled('maintenance_area_id'), fn ($q) => $q->where('maintenance_area_id', $request->integer('maintenance_area_id')))
            ->when($request->filled('maintenance_team_id'), fn ($q) => $q->where('maintenance_team_id', $request->integer('maintenance_team_id')))
            ->when($request->filled('position_id'), fn ($q) => $q->where('position_id', $request->integer('position_id')))
            ->search($request->string('search')->toString())
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        $skillsQuery = Skill::where('is_active', true);

        if ($request->filled('skill_category_id')) {
            $skillsQuery->where('skill_category_id', $request->integer('skill_category_id'));
        }

        $skills = $skillsQuery->orderBy('name')->get();

        $matrix = $employees->map(function (Employee $employee) use ($skills) {
            $currentLevels = $employee->currentSkillAssessments();
            $requirements = $employee->position?->skillRequirements->keyBy('skill_id') ?? collect();

            $cells = $skills->mapWithKeys(function (Skill $skill) use ($currentLevels, $requirements) {
                $current = $currentLevels->get($skill->id)?->competencyLevel;
                $requirement = $requirements->get($skill->id);
                $required = $requirement?->requiredCompetencyLevel;

                $gap = ($current && $required) ? $required->level_number - $current->level_number : null;

                return [$skill->id => [
                    'current' => $current,
                    'required' => $required,
                    'gap' => $gap,
                ]];
            });

            $requiredCells = $cells->filter(fn ($cell) => $cell['required'] !== null);
            if ($requiredCells->isEmpty()) {
                $overallStatus = 'no-requirements';
            } elseif ($requiredCells->contains(fn ($cell) => $cell['current'] === null)) {
                $overallStatus = 'incomplete';
            } elseif ($requiredCells->contains(fn ($cell) => $cell['gap'] > 0)) {
                $overallStatus = 'gap';
            } else {
                $overallStatus = 'meets';
            }

            return [
                'employee' => $employee,
                'cells' => $cells,
                'overall_status' => $overallStatus,
            ];
        });

        return view('skill-matrix.index', [
            'employees' => $employees,
            'skills' => $skills,
            'matrix' => $matrix,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'maintenanceAreas' => MaintenanceArea::where('is_active', true)->orderBy('name')->get(),
            'maintenanceTeams' => MaintenanceTeam::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'skillCategories' => SkillCategory::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['search', 'maintenance_area_id', 'maintenance_team_id', 'position_id', 'skill_category_id']),
        ]);
    }
}
