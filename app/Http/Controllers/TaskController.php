<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskBucket;
use App\Models\TaskChecklistItem;
use App\Models\TaskPlan;
use App\Services\TaskNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private readonly TaskNotifier $notifier) {}

    public function store(Request $request, TaskPlan $plan): RedirectResponse
    {
        $this->authorize('view', $plan);

        $data = $request->validate([
            'task_bucket_id' => ['required', Rule::exists('task_buckets', 'id')->where('task_plan_id', $plan->id)],
            'title' => ['required', 'string', 'max:200'],
        ]);

        $task = $plan->tasks()->create($data + [
            'created_by' => $request->user()->id,
            'start_date' => today(),
            'due_date' => today()->addDays(7),
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

            $lines = $this->describeChanges($task);
            $task->save();

            $sync = $task->assignees()->sync($assigneeIds);
            $actor = $request->user();
            $actorEmployeeId = $actor->employee?->id;

            $this->notifier->assigned($task, $sync['attached'], $actor->name, $actorEmployeeId);

            if ($sync['detached']) {
                $this->notifier->removedFrom($task, $sync['detached'], 'Removed from task', ["{$actor->name} removed you from this task"], $actorEmployeeId);
            }

            // People just assigned already got the "assigned" email - only tell everyone else what changed.
            if ($lines) {
                $this->notifier->changedExcept($task, 'Task updated', array_merge(["Updated by {$actor->name}"], $lines), $actorEmployeeId, $sync['attached']);
            }
        });

        return redirect()->route('tasks.show', $task)->with('status', 'Task saved.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('view', $task->plan);

        $plan = $task->plan;
        $actor = request()->user();
        $this->notifier->removedFrom($task, $task->assignees()->pluck('employees.id'), 'Task deleted', ["{$actor->name} deleted this task"], $actor->employee?->id);
        $task->delete();

        return redirect()->route('tasks.plans.show', $plan)->with('status', 'Task deleted.');
    }

    /** Quick complete / reopen from the board card or My Tasks. */
    public function toggleComplete(Task $task): RedirectResponse
    {
        $this->authorize('view', $task->plan);

        $task->applyProgress($task->progress === 'completed' ? 'in_progress' : 'completed');
        $task->save();

        $actor = request()->user();
        $this->notifier->changed($task, 'Task updated', ["{$actor->name} marked it ".strtolower(Task::progressLabel($task->progress))], $actor->employee?->id);

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

        $bucketChanged = (int) $data['task_bucket_id'] !== $task->task_bucket_id;

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

        if ($bucketChanged) {
            $actor = $request->user();
            $this->notifier->changed($task, 'Task updated', ["{$actor->name} moved it to \"".TaskBucket::find($data['task_bucket_id'])->name.'"'], $actor->employee?->id);
        }

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

    /** Human-readable list of what the pending (unsaved) edit changes, for the "Task updated" email. */
    private function describeChanges(Task $task): array
    {
        $lines = [];
        $fmt = fn ($v) => $v instanceof \Carbon\Carbon ? $v->format('d M Y') : ($v === null || $v === '' ? 'none' : (string) $v);

        foreach (['title' => 'Title', 'priority' => 'Priority', 'start_date' => 'Start date', 'due_date' => 'Due date'] as $field => $label) {
            if ($task->isDirty($field) && $fmt($task->getOriginal($field)) !== $fmt($task->{$field})) {
                $lines[] = "{$label}: {$fmt($task->getOriginal($field))} → {$fmt($task->{$field})}";
            }
        }

        if ($task->isDirty('progress')) {
            $lines[] = 'Progress: '.Task::progressLabel($task->getOriginal('progress')).' → '.Task::progressLabel($task->progress);
        }

        if ($task->isDirty('task_bucket_id')) {
            $lines[] = 'Moved to bucket "'.TaskBucket::find($task->task_bucket_id)->name.'"';
        }

        if ($task->isDirty('description')) {
            $lines[] = 'Notes were edited';
        }

        return $lines;
    }
}
