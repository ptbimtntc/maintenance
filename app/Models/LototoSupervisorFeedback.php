<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One supervisor reply to a LOTOTO follow-up; replies are kept as history, never overwritten. */
#[Fillable(['lototo_activity_id', 'supervisor_employee_id', 'user_id', 'comment'])]
class LototoSupervisorFeedback extends Model
{
    protected $table = 'lototo_supervisor_feedbacks';

    public function activity(): BelongsTo
    {
        return $this->belongsTo(LototoActivity::class, 'lototo_activity_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who replied, as shown next to the reply. */
    public function authorName(): string
    {
        return $this->supervisor?->full_name ?? $this->user?->name ?? 'Supervisor';
    }
}
