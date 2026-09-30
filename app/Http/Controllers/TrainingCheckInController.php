<?php

namespace App\Http\Controllers;

use App\Models\TrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Face check-in for a training session: a shared kiosk device (run by
 * whoever is at the venue) scans whoever is standing in front of it and
 * matches 1-to-many against every participant of *this session* who has
 * already enrolled a face descriptor via their own Profile page (see
 * FaceLoginController) - it never enrolls a face itself. A match marks
 * that participant "attended" and stamps checked_in_at, which is what
 * TrainingParticipant::quizEligibility() already checks to unlock the quiz.
 */
class TrainingCheckInController extends Controller
{
    /**
     * Same distance threshold as Face Login (see FaceLoginController) -
     * kept identical since it's matching against the same kind of
     * face-api.js descriptor and a failed match always falls back to the
     * admin marking attendance manually.
     */
    private const MATCH_THRESHOLD = 0.5;

    public function show(TrainingSession $trainingSession): View
    {
        $trainingSession->load(['trainingProgram', 'location']);

        return view('training.sessions.check-in', [
            'trainingSession' => $trainingSession,
        ]);
    }

    public function attempt(Request $request, TrainingSession $trainingSession): JsonResponse
    {
        $request->validate([
            'descriptor' => ['required', 'array', 'size:128'],
            'descriptor.*' => ['numeric'],
        ]);

        $descriptor = $request->array('descriptor');

        $candidates = $trainingSession->participants()
            ->with('employee.user')
            ->whereHas('employee.user', fn ($q) => $q->whereNotNull('face_descriptor'))
            ->get();

        $best = null;
        $bestDistance = null;

        foreach ($candidates as $participant) {
            $stored = json_decode($participant->employee->user->face_descriptor, true);
            $distance = $this->euclideanDistance($stored, $descriptor);

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $participant;
                $bestDistance = $distance;
            }
        }

        if (! $best || $bestDistance > self::MATCH_THRESHOLD) {
            return response()->json(['message' => 'Face not recognized. Make sure you are enrolled for Face Login on your Profile page and are a participant of this session.'], 422);
        }

        if ($best->attendance_status === 'attended') {
            return response()->json([
                'message' => $best->employee->full_name.' already checked in.',
                'participant' => $best->employee->full_name,
                'already' => true,
            ]);
        }

        $best->forceFill(['attendance_status' => 'attended', 'checked_in_at' => now()])->save();

        return response()->json([
            'message' => 'Checked in: '.$best->employee->full_name,
            'participant' => $best->employee->full_name,
            'already' => false,
        ]);
    }

    private function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return PHP_FLOAT_MAX;
        }

        $sum = 0.0;

        foreach ($a as $i => $value) {
            $sum += ($value - $b[$i]) ** 2;
        }

        return sqrt($sum);
    }
}
