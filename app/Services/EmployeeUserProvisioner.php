<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates the login account for an employee that doesn't have one yet:
 * username = NIK, starting password = NIK (must be changed on first login),
 * role guessed from the position title. Shared by the "Add Employee" form
 * (automatic, one employee at a time) and the `app:provision-employee-users`
 * artisan command (manual, backfills everyone still missing a login).
 */
class EmployeeUserProvisioner
{
    /**
     * Returns the created User, or null if a login already exists for that
     * NIK/email (the employee is left unlinked so an admin can resolve the
     * clash manually via User Management).
     */
    public static function provision(Employee $employee): ?User
    {
        $username = mb_strtolower(trim($employee->employee_number));
        $email = $employee->email ?: Str::slug($username).'@employees.mpd.local';

        if (User::where('username', $username)->orWhere('email', $email)->exists()) {
            return null;
        }

        $user = new User([
            'name' => $employee->full_name,
            'email' => $email,
            'password' => $employee->employee_number,
            'must_change_password' => true,
        ]);
        $user->username = $username;
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole(self::roleFor($employee)->value);

        $employee->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    public static function roleFor(Employee $employee): RoleName
    {
        $title = mb_strtolower((string) $employee->position?->title);

        return match (true) {
            str_contains($title, 'manager'), str_contains($title, 'superintendent') => RoleName::MaintenanceManager,
            str_contains($title, 'supervisor') => RoleName::MaintenanceSupervisor,
            str_contains($title, 'trainer'), str_contains($title, 'specialist') => RoleName::PeopleDevelopment,
            default => RoleName::MaintenanceStaff,
        };
    }
}
