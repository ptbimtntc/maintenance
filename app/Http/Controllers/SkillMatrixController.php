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
    use \App\Concerns\ExportsSpreadsheet;
    use \App\Concerns\FiltersByMultipleIds;

    /**
     * Build an employee x skill competency matrix.
     *
     * Gap = required competency level - current competency level, per the
     * business rule in the project brief. A skill the employee has never
     * been assessed on is shown explicitly as "Not assessed" rather than
     * silently treated as meeting the requirement.
     */
    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $exporting = $request->string('export')->toString() === 'xlsx';

        $maintenanceAreaIds = $this->idList($request->input('maintenance_area_id'));
        $maintenanceTeamIds = $this->idList($request->input('maintenance_team_id'));
        $positionIds = $this->idList($request->input('position_id'));
        $skillCategoryIds = $this->idList($request->input('skill_category_id'));

        $employees = Employee::query()
            ->visibleTo($request->user())
            ->with(['position.skillRequirements.requiredCompetencyLevel', 'maintenanceArea', 'maintenanceTeam'])
            ->when($maintenanceAreaIds !== [], fn ($q) => $q->whereIn('maintenance_area_id', $maintenanceAreaIds))
            ->when($maintenanceTeamIds !== [], fn ($q) => $q->whereIn('maintenance_team_id', $maintenanceTeamIds))
            ->when($positionIds !== [], fn ($q) => $q->whereIn('position_id', $positionIds))
            ->search($request->string('search')->toString())
            ->orderBy('full_name')
            ;

        $employees = $exporting ? $employees->get() : $employees->paginate(15)->withQueryString();

        $skillsQuery = Skill::where('is_active', true);

        if ($skillCategoryIds !== []) {
            $skillsQuery->whereIn('skill_category_id', $skillCategoryIds);
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

        if ($exporting) {
            $header = ['Employee Number', 'Employee', 'Position', 'Overall Status'];
            foreach ($skills as $skill) {
                array_push($header, $skill->name.' - Current', $skill->name.' - Required', $skill->name.' - Gap');
            }

            $rows = $matrix->map(function ($row) use ($skills) {
                $line = [$row['employee']->employee_number, $row['employee']->full_name, $row['employee']->position?->title, $row['overall_status']];
                foreach ($skills as $skill) {
                    $cell = $row['cells'][$skill->id];
                    array_push($line, $cell['current'] ? $cell['current']->level_number.' - '.$cell['current']->name : null, $cell['required'] ? $cell['required']->level_number.' - '.$cell['required']->name : null, $cell['gap']);
                }

                return $line;
            });

            return $this->streamXlsx('skill-matrix-'.now()->format('Y-m-d').'.xlsx', $header, $rows);
        }

        return view('skill-matrix.index', [
            'employees' => $employees,
            'skills' => $skills,
            'matrix' => $matrix,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'maintenanceAreas' => MaintenanceArea::where('is_active', true)->orderBy('name')->get(),
            'maintenanceTeams' => MaintenanceTeam::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'skillCategories' => SkillCategory::where('is_active', true)->orderBy('name')->get(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'maintenance_area_id' => $maintenanceAreaIds,
                'maintenance_team_id' => $maintenanceTeamIds,
                'position_id' => $positionIds,
                'skill_category_id' => $skillCategoryIds,
            ],
        ]);
    }
}
