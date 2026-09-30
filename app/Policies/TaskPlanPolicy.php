<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\TaskPlan;
use App\Models\User;

class TaskPlanPolicy
{
    public function create(User $user): bool
    {
        return ! $user->hasRole(RoleName::Guest->value);
    }

    /** Owner, an assignee on any task in the plan, or an Administrator. */
    public function view(User $user, TaskPlan $plan): bool
    {
        return ! $user->hasRole(RoleName::Guest->value)
            && TaskPlan::query()->visibleTo($user)->whereKey($plan->id)->exists();
    }

    /** Plan settings and buckets: the owner or an Administrator only. */
    public function manage(User $user, TaskPlan $plan): bool
    {
        return $user->id === $plan->owner_id || $user->hasRole(RoleName::Administrator->value);
    }
}
