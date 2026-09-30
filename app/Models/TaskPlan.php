<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'owner_id'])]
class TaskPlan extends Model
{

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function buckets(): HasMany
    {
        return $this->hasMany(TaskBucket::class)->orderBy('position')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Plans the user can open: their own, plus any plan containing a task
     * assigned to their linked employee record. Administrators see all.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole(\App\Enums\RoleName::Administrator->value)) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('owner_id', $user->id);

            if ($user->employee) {
                $q->orWhereHas('tasks.assignees', fn ($a) => $a->where('employees.id', $user->employee->id));
            }
        });
    }
}
