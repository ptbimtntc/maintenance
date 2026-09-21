<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionEmployeeUsers extends Command
{
    protected $signature = 'app:provision-employee-users {--dry-run : Show what would be created without changing anything}';

    protected $description = 'Create a login for every employee that has none: username = NIK, starting password = NIK (must be changed on first login), role chosen from the position.';

    public function handle(): int
    {
        $created = collect();

        Employee::query()->whereNull('user_id')->with('position')->orderBy('employee_number')->each(function (Employee $employee) use ($created) {
            $username = mb_strtolower(trim($employee->employee_number));
            $email = Str::slug($username).'@employees.mpd.local';

            if (User::where('username', $username)->orWhere('email', $email)->exists()) {
                $this->warn("Skipped {$employee->employee_number}: a login with that NIK already exists.");

                return;
            }

            $role = self::roleFor($employee);
            $created->push([$employee->employee_number, $employee->full_name, $employee->position?->title ?? '-', $role->value]);

            if ($this->option('dry-run')) {
                return;
            }

            $user = new User(['name' => $employee->full_name, 'email' => $email, 'password' => $employee->employee_number, 'must_change_password' => true]);
            $user->username = $username;
            $user->email_verified_at = now();
            $user->save();
            $user->assignRole($role->value);

            $employee->forceFill(['user_id' => $user->id])->save();
        });

        $this->table(['NIK', 'Name', 'Position', 'Role'], $created->all());
        $this->info(($this->option('dry-run') ? 'Would create ' : 'Created ').$created->count().' login(s).');

        return self::SUCCESS;
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
