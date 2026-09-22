<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Http\Requests\StoreTrainingProgramRequest;
use App\Models\Location;
use App\Models\Signatory;
use App\Models\Skill;
use App\Models\TrainingCategory;
use App\Models\TrainingProgram;
use App\Models\TrainingProvider;
use App\Models\TrainingType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TrainingProgramController extends Controller
{
    public function index(Request $request): View
    {
        $programs = TrainingProgram::query()
            ->with(['trainingCategory', 'trainingType'])
            ->withCount('sessions')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('training_category_id'), fn ($q) => $q->where('training_category_id', $request->integer('training_category_id')))
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return view('training.programs.index', [
            'programs' => $programs,
            'categories' => TrainingCategory::where('is_active', true)->orderBy('name')->get(),
            'statuses' => TrainingProgram::STATUSES,
            'filters' => $request->only(['status', 'training_category_id']),
        ]);
    }

    public function create(): View
    {
        $this->authorize(PermissionName::ManageTraining->value);

        return view('training.programs.form', [
            'program' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreTrainingProgramRequest $request): RedirectResponse
    {
        $program = TrainingProgram::create([
            ...$request->safe()->except(['skills', 'authorizer_signature', 'trainer_signature']),
            'is_internal' => $request->boolean('is_internal'),
            'created_by' => $request->user()->id,
        ]);

        $program->skills()->sync($request->input('skills', []));
        $this->storeSignatures($request, $program);

        return redirect()->route('training.programs.show', $program)->with('status', 'Training program created.');
    }

    public function show(TrainingProgram $program): View
    {
        $program->load(['trainingCategory', 'trainingType', 'trainingProvider', 'location', 'skills', 'sessions' => fn ($q) => $q->orderByDesc('start_date')]);

        return view('training.programs.show', ['program' => $program]);
    }

    public function edit(TrainingProgram $program): View
    {
        $this->authorize(PermissionName::ManageTraining->value);
        $program->load('skills');

        return view('training.programs.form', [
            'program' => $program,
            ...$this->formOptions(),
        ]);
    }

    public function update(StoreTrainingProgramRequest $request, TrainingProgram $program): RedirectResponse
    {
        $program->update([
            ...$request->safe()->except(['skills', 'authorizer_signature', 'trainer_signature']),
            'is_internal' => $request->boolean('is_internal'),
        ]);

        $program->skills()->sync($request->input('skills', []));
        $this->storeSignatures($request, $program);

        return redirect()->route('training.programs.show', $program)->with('status', 'Training program updated.');
    }

    /**
     * Signatures print on the certificate (certificates/show.blade.php), so
     * they live on the program rather than per-certificate: there's no
     * infrastructure to snapshot an image at issuance the way trainer_name/
     * authorizer_name are copied as plain text.
     *
     * Picking a Signatory (see SignatoryController) fills the name/title/
     * signature image in from that reusable record; a manually uploaded
     * file for this program always wins over the Signatory's image, so an
     * admin can still override a one-off program without touching the
     * shared Signatory record.
     */
    private function storeSignatures(Request $request, TrainingProgram $program): void
    {
        foreach ([
            'authorizer_signature' => ['path' => 'authorizer_signature_path', 'signatory_id' => 'authorizer_signatory_id', 'name' => 'authorizer_name', 'title' => 'authorizer_title'],
            'trainer_signature' => ['path' => 'trainer_signature_path', 'signatory_id' => 'trainer_signatory_id', 'name' => 'trainer_name', 'title' => null],
        ] as $field => $columns) {
            if ($request->hasFile($field)) {
                if ($program->{$columns['path']}) {
                    Storage::disk('public')->delete($program->{$columns['path']});
                }

                $program->forceFill([$columns['path'] => $request->file($field)->store('training-signatures', 'public')])->save();

                continue;
            }

            $signatory = $program->{$columns['signatory_id']} ? Signatory::find($program->{$columns['signatory_id']}) : null;

            if (! $signatory) {
                continue;
            }

            $program->forceFill([
                $columns['name'] => $program->{$columns['name']} ?: $signatory->name,
                $columns['path'] => $signatory->signature_path,
                ...($columns['title'] ? [$columns['title'] => $program->{$columns['title']} ?: $signatory->title] : []),
            ])->save();
        }
    }

    public function destroy(TrainingProgram $program): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTraining->value);

        $program->delete();

        return redirect()->route('training.programs.index')->with('status', 'Training program removed.');
    }

    private function formOptions(): array
    {
        return [
            'categories' => TrainingCategory::where('is_active', true)->orderBy('name')->get(),
            'types' => TrainingType::where('is_active', true)->orderBy('name')->get(),
            'providers' => TrainingProvider::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'skills' => Skill::where('is_active', true)->orderBy('name')->get(),
            'statuses' => TrainingProgram::STATUSES,
            'signatories' => Signatory::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
