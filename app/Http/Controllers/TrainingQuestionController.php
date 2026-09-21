<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\TrainingProgram;
use App\Models\TrainingQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The quiz behind a training program's certificate: multiple-choice
 * questions with four options (A-D), one or more marked correct, each
 * correct option worth some points.
 */
class TrainingQuestionController extends Controller
{
    public function index(TrainingProgram $program): View
    {
        $this->authorize(PermissionName::ViewTraining->value);

        return view('training.questions.index', [
            'program' => $program,
            'questions' => $program->questions()->with('choices')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, TrainingProgram $program): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTraining->value);

        $data = $request->validate([
            'question_text' => ['required', 'string', 'max:2000'],
            'allow_multiple_answers' => ['sometimes', 'boolean'],
            'choices' => ['required', 'array'],
            'choices.*.text' => ['required', 'string', 'max:255'],
            'choices.*.points' => ['nullable', 'integer', 'min:0', 'max:100'],
            'correct' => ['required', 'array', 'min:1'],
            'correct.*' => ['in:A,B,C,D'],
        ], [
            'correct.required' => 'Mark at least one option as the correct answer.',
        ]);

        $multiple = $request->boolean('allow_multiple_answers');

        if (! $multiple && count($data['correct']) > 1) {
            return back()->withInput()->withErrors(['correct' => 'A single-answer question can only have one correct option.']);
        }

        DB::transaction(function () use ($program, $data, $multiple) {
            $question = $program->questions()->create([
                'question_text' => $data['question_text'],
                'allow_multiple_answers' => $multiple,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $label) {
                $question->choices()->create([
                    'option_label' => $label,
                    'choice_text' => $data['choices'][$label]['text'],
                    'is_correct' => in_array($label, $data['correct'], true),
                    'points' => (int) ($data['choices'][$label]['points'] ?? 1) ?: 1,
                ]);
            }
        });

        return redirect()->route('training.programs.questions.index', $program)->with('status', 'Question added.');
    }

    public function destroy(TrainingProgram $program, TrainingQuestion $question): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTraining->value);
        abort_unless($question->training_program_id === $program->id, 404);

        $question->delete();

        return redirect()->route('training.programs.questions.index', $program)->with('status', 'Question removed.');
    }
}
