<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['training_session_id', 'employee_id', 'attendance_status', 'notes'])]
class TrainingParticipant extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingParticipantFactory> */
    use HasFactory;

    public const ATTENDANCE_STATUSES = ['invited', 'confirmed', 'attended', 'absent'];

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
