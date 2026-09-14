<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /**
     * Anyone who can see at least one employee (their own, their team's, or
     * all of them) may reach the index. The controller narrows the actual
     * query based on which of these the user holds.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            PermissionName::ViewAllEmployees->value,
            PermissionName::ViewTeamEmployees->value,
            PermissionName::ViewOwnEmployee->value,
        ]);
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->hasPermissionTo(PermissionName::ViewAllEmployees->value)) {
            return true;
        }

        if ($user->hasPermissionTo(PermissionName::ViewTeamEmployees->value)) {
            $viewerTeamId = $user->employee?->maintenance_team_id;

            if ($viewerTeamId !== null && $viewerTeamId === $employee->maintenance_team_id) {
                return true;
            }
        }

        if ($user->hasPermissionTo(PermissionName::ViewOwnEmployee->value)) {
            return $employee->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::ManageEmployees->value);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo(PermissionName::ManageEmployees->value);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo(PermissionName::ManageEmployees->value);
    }
}
