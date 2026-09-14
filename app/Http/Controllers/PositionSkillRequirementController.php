<?php

namespace App\Http\Controllers;

use App\Models\CompetencyLevel;
use App\Models\Position;
use App\Models\PositionSkillRequirement;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionSkillRequirementController extends Controller
{
    public function index(): View
    {
        $positions = Position::where('is_active', true)
            ->withCount('skillRequirements')
            ->orderBy('title')
            ->paginate(20);

        return view('skills.positions-index', ['positions' => $positions]);
    }

    public function edit(Position $position): View
    {
        $position->load(['skillRequirements.skill', 'skillRequirements.requiredCompetencyLevel']);

        return view('skills.position-requirements', [
            'position' => $position,
            'skills' => Skill::where('is_active', true)->orderBy('name')->get(),
            'competencyLevels' => CompetencyLevel::where('is_active', true)->orderBy('level_number')->get(),
        ]);
    }

    public function store(Request $request, Position $position): RedirectResponse
    {
        $data = $request->validate([
            'skill_id' => [
                'required',
                'exists:skills,id',
                Rule::unique('position_skill_requirements')->where('position_id', $position->id),
            ],
            'required_competency_level_id' => ['required', 'exists:competency_levels,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $position->skillRequirements()->create($data);

        return redirect()->route('skills.positions.edit', $position)->with('status', 'Skill requirement added.');
    }

    public function destroy(Position $position, PositionSkillRequirement $requirement): RedirectResponse
    {
        abort_unless($requirement->position_id === $position->id, 404);

        $requirement->delete();

        return redirect()->route('skills.positions.edit', $position)->with('status', 'Skill requirement removed.');
    }
}
