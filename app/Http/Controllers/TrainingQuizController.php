<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\TrainingParticipant;
use App\Models\TrainingRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The employee-facing half of the certificate flow: an admin schedules the
 * training and marks the participant "attended"; on the training day the
 * employee takes the quiz here. Passing (score >= the program's passing
 * score) issues a numbered, verified certificate automatically; a fail
 * records the attempt and issues nothing.
 */
class TrainingQuizController extends Controller
{
    public function show(Request $request, TrainingParticipant $participant): View
    {
        $this->authorizeOwner($request, $participant);
        $participant->load(['trainingSession.trainingProgram.questions.choices', 'employee', 'certificate']);

        [$eligible, $reason] = $participant->quizEligibility();

        return view('training.quiz.show', [
            'participant' => $participant,
            'program' => $participant->trainingSession->trainingProgram,
            'eligible' => $eligible,
            'message' => $eligible ? null : TrainingParticipant::quizMessage($reason, $participant->trainingSession),
            'questions' => $eligible ? $participant->trainingSession->trainingProgram->questions : collect(),
        ]);
    }

    public function submit(Request $request, TrainingParticipant $participant): RedirectResponse
    {
        $this->authorizeOwner($request, $participant);

        DB::transaction(function () use ($request, $participant) {
            $participant = TrainingParticipant::query()->lockForUpdate()->with(['trainingSession.trainingProgram', 'employee'])->findOrFail($participant->id);

            [$eligible] = $participant->quizEligibility();
            abort_unless($eligible, 403, 'The quiz is not available.');

            $program = $participant->trainingSession->trainingProgram;
            $questions = $program->questions()->with('choices')->get();
            $submitted = $request->input('answers', []);

            $earned = 0;
            $possible = 0;
            $answerRows = [];

            foreach ($questions as $question) {
                $correct = $question->choices->where('is_correct', true);
                $possible += $correct->sum('points');

                $picked = collect((array) ($submitted[$question->id] ?? []))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $question->choices->contains('id', $id))
                    ->unique()->sort()->values();

                if ($picked->all() === $correct->pluck('id')->sort()->values()->all()) {
                    $earned += $correct->sum('points');
                }

                foreach ($picked as $choiceId) {
                    $answerRows[] = ['training_participant_id' => $participant->id, 'training_question_id' => $question->id, 'training_question_choice_id' => $choiceId, 'created_at' => now(), 'updated_at' => now()];
                }
            }

            $score = $possible > 0 ? (int) round($earned / $possible * 100) : 0;
            $passed = $program->passing_score === null || $score >= $program->passing_score;
            $trainingDate = now()->startOfDay();

            DB::table('training_quiz_answers')->insert($answerRows);

            $certificate = null;

            if ($passed) {
                $previous = Certificate::where('employee_id', $participant->employee_id)
                    ->where('related_training_program_id', $program->id)->latest('id')->first();

                $certificate = Certificate::create([
                    'employee_id' => $participant->employee_id,
                    'name' => $program->title,
                    'certificate_number' => Certificate::generateNumber($program, $trainingDate),
                    'issuing_organization' => 'PT Bekaert Indonesia',
                    'trainer_name' => $participant->trainingSession->trainer_name ?: $program->trainer_name,
                    'authorizer_name' => $program->authorizer_name,
                    'authorizer_title' => $program->authorizer_title ?: 'Maintenance Manager',
                    'issue_date' => $trainingDate,
                    'expiry_date' => $program->validity_months ? $trainingDate->copy()->addMonths($program->validity_months) : null,
                    'related_training_program_id' => $program->id,
                    'verification_status' => 'verified',
                    'previous_certificate_id' => $previous?->id,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
            }

            TrainingRecord::create([
                'employee_id' => $participant->employee_id,
                'training_program_id' => $program->id,
                'training_session_id' => $participant->training_session_id,
                'training_date' => $trainingDate,
                'trainer_name' => $participant->trainingSession->trainer_name ?: $program->trainer_name,
                'attendance_status' => 'attended',
                'completion_status' => $passed ? 'completed' : 'failed',
                'assessment_score' => $score,
                'assessment_result' => $passed ? 'pass' : 'fail',
                'certificate_issued' => $passed,
                'certificate_reference' => $certificate?->certificate_number,
                'recorded_by' => $request->user()->id,
                'record_date' => now(),
            ]);

            $participant->forceFill([
                'quiz_submitted_at' => now(),
                'quiz_score' => $score,
                'certificate_id' => $certificate?->id,
            ])->save();
        });

        return redirect()->route('training.quiz.show', $participant)->with('status', 'Your answers have been submitted.');
    }

    private function authorizeOwner(Request $request, TrainingParticipant $participant): void
    {
        abort_unless($participant->employee?->user_id === $request->user()->id, 403);
    }
}
