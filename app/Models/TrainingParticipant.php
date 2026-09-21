<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['training_session_id', 'employee_id', 'attendance_status', 'notes', 'quiz_submitted_at', 'quiz_score', 'certificate_id'])]
class TrainingParticipant extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingParticipantFactory> */
    use HasFactory;

    public const ATTENDANCE_STATUSES = ['invited', 'confirmed', 'attended', 'absent'];

    protected function casts(): array
    {
        return ['quiz_submitted_at' => 'datetime'];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /**
     * Whether the quiz can be taken right now, mirroring the competency
     * flow: not yet submitted, attendance confirmed by an admin
     * ("attended"), the training is actually today, and the program has
     * questions. Returns [bool eligible, string reason].
     */
    public function quizEligibility(): array
    {
        $session = $this->trainingSession;

        return match (true) {
            $this->quiz_submitted_at !== null => [false, 'already_submitted'],
            $this->attendance_status !== 'attended' => [false, 'not_confirmed'],
            ! now()->between($session->start_date->copy()->startOfDay(), $session->end_date->copy()->endOfDay()) => [false, 'wrong_date'],
            $session->trainingProgram->questions()->doesntExist() => [false, 'no_questions'],
            default => [true, 'ok'],
        };
    }

    public static function quizMessage(string $reason, ?TrainingSession $session = null): string
    {
        return match ($reason) {
            'already_submitted' => 'The quiz for this training has already been submitted.',
            'not_confirmed' => 'Your attendance for this training has not been confirmed by an admin yet.',
            'wrong_date' => 'The quiz can only be taken during the training dates'.($session ? ': '.$session->start_date->format('d M Y').($session->end_date->ne($session->start_date) ? ' - '.$session->end_date->format('d M Y') : '') : '').'.',
            'no_questions' => 'The quiz questions for this training are not available yet.',
            default => 'The quiz is not available right now.',
        };
    }

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
