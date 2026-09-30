<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'task_plan_id', 'task_bucket_id', 'title', 'description', 'priority', 'progress',
    'start_date', 'due_date', 'position', 'completed_at', 'created_by',
])]
class Task extends Model
{

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const PROGRESS = ['not_started', 'in_progress', 'completed'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TaskPlan::class, 'task_plan_id');
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(TaskBucket::class, 'task_bucket_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_assignees')->withTimestamps();
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    public function isOverdue(): bool
    {
        return $this->progress !== 'completed' && $this->due_date !== null && $this->due_date->lt(now()->startOfDay());
    }

    /** Keeps completed_at in step with progress so "done when" is never stale. */
    public function applyProgress(string $progress): void
    {
        $this->progress = $progress;
        $this->completed_at = $progress === 'completed' ? ($this->completed_at ?? now()) : null;
    }

    public static function progressLabel(string $progress): string
    {
        return match ($progress) {
            'not_started' => 'Not started',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            default => $progress,
        };
    }
}
