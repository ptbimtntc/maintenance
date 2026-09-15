<?php

namespace App\Policies;

use App\Enums\MenuKey;
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
            PermissionName::ViewSubordinateEmployees->value,
            PermissionName::ViewOwnEmployee->value,
        ]);
    }

    /**
     * Delegates to the same Employee::scopeVisibleTo() used for list
     * screens, so a single-record check (e.g. certificate download, the
     * profile page) can never disagree with what shows up in a list.
     */
    public function view(User $user, Employee $employee): bool
    {
        return Employee::query()->visibleTo($user)->whereKey($employee->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::ManageEmployees->value)
            && $user->canEditMenu(MenuKey::Employees);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo(PermissionName::ManageEmployees->value)
            && $user->canEditMenu(MenuKey::Employees)
            && $this->view($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee);
    }
}
