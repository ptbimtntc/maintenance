<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeUserProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProvisionEmployeeUsers extends Command
{
    protected $signature = 'app:provision-employee-users {--dry-run : Show what would be created without changing anything}';

    protected $description = 'Create a login for every employee that has none: username = NIK, starting password = NIK (must be changed on first login), role chosen from the position.';

    public function handle(): int
    {
        $created = collect();

        Employee::query()->whereNull('user_id')->with('position')->orderBy('employee_number')->each(function (Employee $employee) use ($created) {
            $role = EmployeeUserProvisioner::roleFor($employee);

            if ($this->option('dry-run')) {
                $username = mb_strtolower(trim($employee->employee_number));
                $email = $employee->email ?: Str::slug($username).'@employees.mpd.local';

                if (User::where('username', $username)->orWhere('email', $email)->exists()) {
                    $this->warn("Skipped {$employee->employee_number}: a login with that NIK already exists.");

                    return;
                }

                $created->push([$employee->employee_number, $employee->full_name, $employee->position?->title ?? '-', $role->value]);

                return;
            }

            $user = EmployeeUserProvisioner::provision($employee);

            if (! $user) {
                $this->warn("Skipped {$employee->employee_number}: a login with that NIK already exists.");

                return;
            }

            $created->push([$employee->employee_number, $employee->full_name, $employee->position?->title ?? '-', $role->value]);
        });

        $this->table(['NIK', 'Name', 'Position', 'Role'], $created->all());
        $this->info(($this->option('dry-run') ? 'Would create ' : 'Created ').$created->count().' login(s).');

        return self::SUCCESS;
    }
}
