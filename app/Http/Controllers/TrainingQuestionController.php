<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsSpreadsheet;
use App\Enums\PermissionName;
use App\Models\TrainingProgram;
use App\Models\TrainingQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The quiz behind a training program's certificate: multiple-choice
 * questions with four options (A-D), one or more marked correct, each
 * correct option worth some points.
 */
class TrainingQuestionController extends Controller
{
    use ExportsSpreadsheet;

    /**
     * Column headers both the "Export XLSX" download and the "Import XLSX"
     * upload use, in this order.
     */
    private const COLUMNS = [
        'Question', 'Allow Multiple Answers',
        'Option A', 'Option B', 'Option C', 'Option D',
        'Points A', 'Points B', 'Points C', 'Points D',
        'Correct (e.g. B or A,C)',
    ];

    public function index(TrainingProgram $program): View
    {
        $this->authorize(PermissionName::ViewTraining->value);

        return view('training.questions.index', [
            'program' => $program,
            'questions' => $program->questions()->with('choices')->orderBy('id')->get(),
        ]);
    }

    public function export(TrainingProgram $program): StreamedResponse
    {
        $this->authorize(PermissionName::ViewTraining->value);

        $rows = $program->questions()->with('choices')->orderBy('id')->get()->map(function (TrainingQuestion $question) {
            $choices = $question->choices->keyBy('option_label');

            return [
                $question->question_text,
                $question->allow_multiple_answers ? 'Yes' : 'No',
                $choices['A']->choice_text ?? '',
                $choices['B']->choice_text ?? '',
                $choices['C']->choice_text ?? '',
                $choices['D']->choice_text ?? '',
                $choices['A']->points ?? 1,
                $choices['B']->points ?? 1,
                $choices['C']->points ?? 1,
                $choices['D']->points ?? 1,
                $choices->filter(fn ($c) => $c->is_correct)->keys()->implode(','),
            ];
        });

        $filename = 'questions-'.\Illuminate\Support\Str::slug($program->title).'-'.now()->format('Y-m-d').'.xlsx';

        return $this->streamXlsx($filename, self::COLUMNS, $rows);
    }

    /**
     * Replaces the program's entire question bank with the uploaded rows.
     * There's no natural key to match an existing question against once
     * it's been edited in Excel, so a full replace (inside a transaction)
     * is the only option that doesn't accumulate duplicates on re-import.
     */
    public function import(Request $request, TrainingProgram $program): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTraining->value);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $rows = (new XlsxReader)->load($request->file('file')->getRealPath())
            ->getActiveSheet()
            ->toArray(null, true, true, false);

        $header = array_map(fn ($cell) => trim((string) $cell), array_shift($rows) ?? []);

        $parsed = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = array_combine($header, array_pad($row, count($header), null));

            $questionText = trim((string) ($data['Question'] ?? ''));

            if ($questionText === '') {
                continue;
            }

            $options = [];
            $missingOption = false;

            foreach (['A', 'B', 'C', 'D'] as $label) {
                $text = trim((string) ($data["Option {$label}"] ?? ''));

                if ($text === '') {
                    $errors[] = "Row {$rowNumber}: Option {$label} is required — question skipped.";
                    $missingOption = true;

                    continue;
                }

                $points = (int) ($data["Points {$label}"] ?? 1) ?: 1;
                $options[$label] = ['text' => $text, 'points' => max(0, min(100, $points))];
            }

            if ($missingOption) {
                continue;
            }

            $correctLabels = array_values(array_filter(array_map('trim', explode(',', strtoupper((string) ($data['Correct (e.g. B or A,C)'] ?? ''))))));
            $correctLabels = array_intersect($correctLabels, ['A', 'B', 'C', 'D']);

            if ($correctLabels === []) {
                $errors[] = "Row {$rowNumber}: no valid Correct option (A-D) given — question skipped.";

                continue;
            }

            $allowMultiple = in_array(strtolower(trim((string) ($data['Allow Multiple Answers'] ?? ''))), ['yes', 'true', '1'], true);

            if (! $allowMultiple && count($correctLabels) > 1) {
                $errors[] = "Row {$rowNumber}: multiple Correct options given but Allow Multiple Answers isn't Yes — only the first ({$correctLabels[0]}) was kept.";
                $correctLabels = [$correctLabels[0]];
            }

            $parsed[] = [
                'question_text' => $questionText,
                'allow_multiple_answers' => $allowMultiple,
                'options' => $options,
                'correct' => $correctLabels,
            ];
        }

        if ($parsed === []) {
            return back()->with('import_errors', 'No valid questions found in the file. '.implode(' | ', array_slice($errors, 0, 8)));
        }

        DB::transaction(function () use ($program, $parsed) {
            $program->questions()->delete();

            foreach ($parsed as $row) {
                $question = $program->questions()->create([
                    'question_text' => $row['question_text'],
                    'allow_multiple_answers' => $row['allow_multiple_answers'],
                ]);

                foreach ($row['options'] as $label => $option) {
                    $question->choices()->create([
                        'option_label' => $label,
                        'choice_text' => $option['text'],
                        'is_correct' => in_array($label, $row['correct'], true),
                        'points' => $option['points'],
                    ]);
                }
            }
        });

        $redirect = redirect()->route('training.programs.questions.index', $program)
            ->with('status', count($parsed).' question(s) imported — the existing question bank was replaced.');

        if ($errors !== []) {
            $shown = array_slice($errors, 0, 8);
            $suffix = count($errors) > 8 ? ' …and '.(count($errors) - 8).' more.' : '';
            $redirect->with('import_errors', count($errors).' issue(s) found: '.implode(' | ', $shown).$suffix);
        }

        return $redirect;
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
