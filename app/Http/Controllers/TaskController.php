<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskBucket;
use App\Models\TaskChecklistItem;
use App\Models\TaskPlan;
use App\Notifications\TaskAssigned;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function store(Request $request, TaskPlan $plan): RedirectResponse
    {
        $this->authorize('view', $plan);

        $data = $request->validate([
            'task_bucket_id' => ['required', Rule::exists('task_buckets', 'id')->where('task_plan_id', $plan->id)],
            'title' => ['required', 'string', 'max:200'],
        ]);

        $task = $plan->tasks()->create($data + [
            'created_by' => $request->user()->id,
            'position' => (Task::where('task_bucket_id', $data['task_bucket_id'])->max('position') ?? -1) + 1,
        ]);

        return redirect()->route('tasks.show', $task)->with('status', 'Task created - fill in the details below.');
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('view', $task->plan);

        $task->load(['plan.buckets', 'assignees', 'checklistItems', 'creator']);

        return view('tasks.show', [
            'task' => $task,
            'employees' => Employee::query()->orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
            'canManage' => $request->user()->can('manage', $task->plan),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task->plan);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'task_bucket_id' => ['required', Rule::exists('task_buckets', 'id')->where('task_plan_id', $task->task_plan_id)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'progress' => ['required', Rule::in(Task::PROGRESS)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $assigneeIds = $data['assignee_ids'] ?? [];
        $progress = $data['progress'];
        unset($data['assignee_ids'], $data['progress']);

        DB::transaction(function () use ($task, $data, $progress, $assigneeIds, $request) {
            $bucketChanged = (int) $data['task_bucket_id'] !== $task->task_bucket_id;

            $task->fill($data);
            $task->applyProgress($progress);

            if ($bucketChanged) {
                $task->position = (Task::where('task_bucket_id', $data['task_bucket_id'])->max('position') ?? -1) + 1;
            }

            $task->save();

            $changes = $task->assignees()->sync($assigneeIds);
            $this->notifyNewAssignees($task, $changes['attached'], $request->user()->name, $request->user()->employee?->id);
        });

        return redirect()->route('tasks.show', $task)->with('status', 'Task saved.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('view', $task->plan);

        $plan = $task->plan;
        $task->delete();

        return redirect()->route('tasks.plans.show', $plan)->with('status', 'Task deleted.');
    }

    /** Quick complete / reopen from the board card or My Tasks. */
    public function toggleComplete(Task $task): RedirectResponse
    {
        $this->authorize('view', $task->plan);

        $task->applyProgress($task->progress === 'completed' ? 'in_progress' : 'completed');
        $task->save();

        return back();
    }

    /** Drag-and-drop on the board: place the task at $position within $bucket. */
    public function move(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task->plan);

        $data = $request->validate([
            'task_bucket_id' => ['required', Rule::exists('task_buckets', 'id')->where('task_plan_id', $task->task_plan_id)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($task, $data) {
            $ids = Task::where('task_bucket_id', $data['task_bucket_id'])
                ->whereKeyNot($task->id)
                ->orderBy('position')->orderBy('id')
                ->pluck('id')
                ->all();

            array_splice($ids, min($data['position'], count($ids)), 0, [$task->id]);

            $task->update(['task_bucket_id' => $data['task_bucket_id']]);

            foreach ($ids as $i => $id) {
                Task::whereKey($id)->update(['position' => $i]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function storeChecklistItem(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task->plan);

        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);

        $task->checklistItems()->create($data + ['position' => ($task->checklistItems()->max('position') ?? -1) + 1]);

        return back();
    }

    public function toggleChecklistItem(Task $task, TaskChecklistItem $item): RedirectResponse
    {
        $this->authorize('view', $task->plan);
        abort_unless($item->task_id === $task->id, 404);

        $item->update(['is_done' => ! $item->is_done]);

        return back();
    }

    public function destroyChecklistItem(Task $task, TaskChecklistItem $item): RedirectResponse
    {
        $this->authorize('view', $task->plan);
        abort_unless($item->task_id === $task->id, 404);

        $item->delete();

        return back();
    }

    /** Only newly-added assignees who have a login are notified, and never yourself. */
    private function notifyNewAssignees(Task $task, array $employeeIds, string $assignerName, ?int $assignerEmployeeId): void
    {
        $task->loadMissing('plan');

        Employee::with('user')
            ->whereIn('id', array_diff($employeeIds, [$assignerEmployeeId]))
            ->get()
            ->each(fn (Employee $e) => $e->user?->notify(new TaskAssigned($task, $assignerName)));
    }
}
