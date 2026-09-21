<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsSpreadsheet;
use App\Models\CompetencyLevel;
use App\Models\Position;
use App\Models\PositionSkillRequirement;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PositionSkillRequirementController extends Controller
{
    use ExportsSpreadsheet;

    private const SHEET_HEADER = ['Position', 'Skill', 'Required Level', 'Notes'];

    public function export(): StreamedResponse
    {
        $rows = PositionSkillRequirement::query()
            ->with(['position', 'skill', 'requiredCompetencyLevel'])
            ->get()
            ->sortBy(fn ($r) => [$r->position?->title, $r->skill?->name])
            ->map(fn ($r) => [
                $r->position?->title,
                $r->skill?->name,
                $r->requiredCompetencyLevel ? $r->requiredCompetencyLevel->level_number.' - '.$r->requiredCompetencyLevel->name : null,
                $r->notes,
            ])
            ->values();

        return $this->streamXlsx('position-skill-requirements-'.now()->format('Y-m-d').'.xlsx', self::SHEET_HEADER, $rows);
    }

    /**
     * Bulk set/update requirements from an .xlsx shaped like export(). One
     * row = one (Position, Skill) pair: an existing pair has its required
     * level updated (Notes only when filled in), a new pair is added. The
     * Position and Skill must already exist; nothing is ever removed.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx']]);

        $rows = (new XlsxReader)->load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        $header = array_map(fn ($cell) => trim((string) $cell), array_shift($rows) ?? []);

        if (array_diff(['Position', 'Skill', 'Required Level'], $header)) {
            return redirect()->route('skills.positions.index')
                ->with('import_errors', 'The file needs Position, Skill and Required Level columns - use the Export XLSX file as the template.');
        }

        $positions = Position::all()->groupBy(fn ($p) => mb_strtolower($p->title));
        $skills = Skill::all()->keyBy(fn ($s) => mb_strtolower($s->name));
        $levels = CompetencyLevel::all();

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = array_combine($header, array_pad($row, count($header), null));
            $positionTitle = trim((string) ($data['Position'] ?? ''));
            $skillName = trim((string) ($data['Skill'] ?? ''));
            $levelValue = trim((string) ($data['Required Level'] ?? ''));
            $notes = trim((string) ($data['Notes'] ?? ''));

            if ($positionTitle === '' && $skillName === '' && $levelValue === '') {
                continue;
            }

            $matches = $positions->get(mb_strtolower($positionTitle), collect());

            if ($matches->count() !== 1) {
                $errors[] = $matches->isEmpty()
                    ? "Row {$rowNumber}: Position \"{$positionTitle}\" not found."
                    : "Row {$rowNumber}: Position \"{$positionTitle}\" is ambiguous (several positions share that title).";

                continue;
            }

            $skill = $skills->get(mb_strtolower($skillName));

            if (! $skill) {
                $errors[] = "Row {$rowNumber}: Skill \"{$skillName}\" not found.";

                continue;
            }

            // Accepts "3 - Proficient" (the export format), "3", or the level's name.
            $levelNumber = (int) $levelValue;
            $level = $levels->first(fn ($l) => ($levelNumber > 0 && $l->level_number === $levelNumber) || mb_strtolower($l->name) === mb_strtolower($levelValue));

            if (! $level) {
                $errors[] = "Row {$rowNumber}: Required Level \"{$levelValue}\" not recognized.";

                continue;
            }

            $requirement = PositionSkillRequirement::firstOrNew(['position_id' => $matches->first()->id, 'skill_id' => $skill->id]);
            $isNew = ! $requirement->exists;
            $requirement->required_competency_level_id = $level->id;

            if ($notes !== '') {
                $requirement->notes = $notes;
            }

            $requirement->save();
            $isNew ? $created++ : $updated++;
        }

        $redirect = redirect()->route('skills.positions.index')
            ->with('status', "{$created} requirement(s) added, {$updated} updated from import.");

        if ($errors !== []) {
            $shown = array_slice($errors, 0, 8);
            $suffix = count($errors) > 8 ? ' …and '.(count($errors) - 8).' more.' : '';
            $redirect->with('import_errors', count($errors).' issue(s) found: '.implode(' | ', $shown).$suffix);
        }

        return $redirect;
    }

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
