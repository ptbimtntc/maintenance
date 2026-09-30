<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Task;
use App\Notifications\TaskActivity;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Every task email/notification goes through here so the rules live in one
 * place: the person who made the change is never notified about their own
 * action, an employee with a login gets the in-app notification plus email
 * (to their account email), and an employee with no login but an email on
 * their employee record still gets the email.
 */
class TaskNotifier
{
    public function assigned(Task $task, Collection|array $employeeIds, string $actorName, ?int $actorEmployeeId): void
    {
        $task->loadMissing('plan');

        $this->each($employeeIds, $actorEmployeeId, function (Employee $e) use ($task, $actorName) {
            $this->send($e, new TaskAssigned($task, $actorName, $e->full_name));
        });
    }

    /** @param  string[]  $lines */
    public function changed(Task $task, string $title, array $lines, ?int $actorEmployeeId, bool $withLink = true): void
    {
        $this->each($task->assignees()->pluck('employees.id'), $actorEmployeeId, function (Employee $e) use ($task, $title, $lines, $withLink) {
            $this->send($e, new TaskActivity($title, $task->title, $lines, $withLink ? route('tasks.show', $task) : null, $e->full_name));
        });
    }

    /** Same as changed(), skipping employees who were just assigned in the same save. @param  string[]  $lines */
    public function changedExcept(Task $task, string $title, array $lines, ?int $actorEmployeeId, array $exceptEmployeeIds): void
    {
        $ids = $task->assignees()->pluck('employees.id')->reject(fn ($id) => in_array($id, $exceptEmployeeIds, true));

        $this->each($ids, $actorEmployeeId, function (Employee $e) use ($task, $title, $lines) {
            $this->send($e, new TaskActivity($title, $task->title, $lines, route('tasks.show', $task), $e->full_name));
        });
    }

    /** @param  string[]  $lines */
    public function removedFrom(Task $task, Collection|array $employeeIds, string $title, array $lines, ?int $actorEmployeeId): void
    {
        $this->each($employeeIds, $actorEmployeeId, function (Employee $e) use ($task, $title, $lines) {
            $this->send($e, new TaskActivity($title, $task->title, $lines, null, $e->full_name));
        });
    }

    private function each(Collection|array $employeeIds, ?int $actorEmployeeId, \Closure $callback): void
    {
        Employee::with('user')
            ->whereIn('id', collect($employeeIds)->reject(fn ($id) => $id === $actorEmployeeId)->all())
            ->get()
            ->each($callback);
    }

    private function send(Employee $employee, $notification): void
    {
        if ($employee->user) {
            $employee->user->notify($notification);
        } elseif ($employee->email) {
            Notification::route('mail', [$employee->email => $employee->full_name])->notify($notification);
        }
    }
}
