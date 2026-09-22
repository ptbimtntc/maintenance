<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Http\Requests\StoreTrainingSessionRequest;
use App\Models\Employee;
use App\Models\Location;
use App\Models\TrainingParticipant;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrainingSessionController extends Controller
{
    /**
     * Two views of the same data: a list grouped by month (the original,
     * good for scanning many sessions at once) and a month grid (better for
     * seeing at a glance which days/weeks are busy). Switched with ?view=,
     * grid navigates months with ?month=YYYY-MM.
     */
    public function calendar(Request $request): View
    {
        $view = $request->string('view')->toString() === 'grid' ? 'grid' : 'list';

        if ($view === 'grid') {
            return $this->calendarGrid($request);
        }

        $sessions = TrainingSession::query()
            ->with(['trainingProgram', 'location'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('start_date')
            ->get()
            ->groupBy(fn (TrainingSession $session) => $session->start_date->format('F Y'));

        return view('training.sessions.calendar', [
            'view' => 'list',
            'sessionsByMonth' => $sessions,
            'statuses' => TrainingSession::STATUSES,
            'filters' => $request->only(['status']),
        ]);
    }

    private function calendarGrid(Request $request): View
    {
        $month = Carbon::parse($request->string('month')->toString() ?: now()->format('Y-m-01'))->startOfMonth();

        $sessions = TrainingSession::query()
            ->with(['trainingProgram', 'location'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->where('start_date', '<=', $month->copy()->endOfMonth())
            ->where('end_date', '>=', $month->copy()->startOfMonth())
            ->orderBy('start_date')
            ->get();

        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY);

        $weeks = [];
        $day = $gridStart->copy();
        while ($day->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = [
                    'date' => $day->copy(),
                    'inMonth' => $day->month === $month->month,
                    'sessions' => $sessions->filter(fn (TrainingSession $s) => $day->between($s->start_date, $s->end_date))->values(),
                ];
                $day->addDay();
            }
            $weeks[] = $week;
        }

        return view('training.sessions.calendar', [
            'view' => 'grid',
            'month' => $month,
            'weeks' => $weeks,
            'statuses' => TrainingSession::STATUSES,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(TrainingProgram $program): View
    {
        $this->authorize(PermissionName::ManageTraining->value);

        return view('training.sessions.form', [
            'program' => $program,
            'session' => null,
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'statuses' => TrainingSession::STATUSES,
        ]);
    }

    public function store(StoreTrainingSessionRequest $request, TrainingProgram $program): RedirectResponse
    {
        $session = $program->sessions()->create($request->validated());

        return redirect()->route('training.programs.show', $program)->with('status', "Session added (starts {$session->start_date->format('d M Y')}).");
    }

    public function show(TrainingSession $trainingSession): View
    {
        $trainingSession->load(['trainingProgram', 'location', 'participants.employee']);

        return view('training.sessions.show', [
            'trainingSession' => $trainingSession,
            'availableEmployees' => Employee::query()
                ->whereNotIn('id', $trainingSession->participants->pluck('employee_id'))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number']),
            'attendanceStatuses' => TrainingParticipant::ATTENDANCE_STATUSES,
        ]);
    }

    public function edit(TrainingSession $trainingSession): View
    {
        $this->authorize(PermissionName::ManageTraining->value);

        return view('training.sessions.form', [
            'program' => $trainingSession->trainingProgram,
            'session' => $trainingSession,
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'statuses' => TrainingSession::STATUSES,
        ]);
    }

    public function update(StoreTrainingSessionRequest $request, TrainingSession $trainingSession): RedirectResponse
    {
        $trainingSession->update($request->validated());

        return redirect()->route('training.sessions.show', $trainingSession)->with('status', 'Session updated.');
    }

    public function destroy(TrainingSession $trainingSession): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTraining->value);
        $program = $trainingSession->trainingProgram;

        $trainingSession->delete();

        return redirect()->route('training.programs.show', $program)->with('status', 'Session removed.');
    }

    public function addParticipant(Request $request, TrainingSession $trainingSession): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTraining->value);

        $data = $request->validate([
            'employee_id' => [
                'required',
                'exists:employees,id',
                Rule::unique('training_participants')->where('training_session_id', $trainingSession->id),
            ],
        ]);

        if ($trainingSession->max_participants && $trainingSession->participants()->count() >= $trainingSession->max_participants) {
            return back()->withErrors(['employee_id' => 'This session has already reached its maximum number of participants.']);
        }

        $trainingSession->participants()->create($data);

        return back()->with('status', 'Participant added.');
    }

    public function updateParticipant(Request $request, TrainingSession $trainingSession, TrainingParticipant $participant): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTraining->value);
        abort_unless($participant->training_session_id === $trainingSession->id, 404);

        $data = $request->validate([
            'attendance_status' => ['required', Rule::in(TrainingParticipant::ATTENDANCE_STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $participant->update($data);

        return back()->with('status', 'Attendance updated.');
    }

    public function removeParticipant(TrainingSession $trainingSession, TrainingParticipant $participant): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTraining->value);
        abort_unless($participant->training_session_id === $trainingSession->id, 404);

        $participant->delete();

        return back()->with('status', 'Participant removed.');
    }

    public function showParticipantQuiz(TrainingSession $trainingSession, TrainingParticipant $participant): View
    {
        abort_unless($participant->training_session_id === $trainingSession->id, 404);

        $participant->load(['employee', 'certificate', 'trainingSession.trainingProgram.questions.choices']);

        $selectedChoiceIds = collect();
        if ($participant->quiz_submitted_at) {
            $selectedChoiceIds = DB::table('training_quiz_answers')
                ->where('training_participant_id', $participant->id)
                ->pluck('training_question_choice_id');
        }

        return view('training.sessions.participant-quiz', [
            'trainingSession' => $trainingSession,
            'participant' => $participant,
            'program' => $trainingSession->trainingProgram,
            'selectedChoiceIds' => $selectedChoiceIds,
        ]);
    }
}
