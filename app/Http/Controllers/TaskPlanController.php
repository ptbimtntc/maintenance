<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskBucket;
use App\Models\TaskPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskPlanController extends Controller
{
    /** "My Tasks" across every plan, plus the plans the user can open. */
    public function index(Request $request): View
    {
        $this->authorize('create', TaskPlan::class);

        $user = $request->user();

        $myTasks = $user->employee
            ? Task::query()
                ->whereHas('assignees', fn ($q) => $q->where('employees.id', $user->employee->id))
                ->with(['plan', 'bucket', 'assignees', 'checklistItems'])
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->get()
            : collect();

        $today = now()->startOfDay();
        $groups = [
            'Overdue' => $myTasks->filter(fn (Task $t) => $t->isOverdue()),
            'Due within 7 days' => $myTasks->filter(fn (Task $t) => $t->progress !== 'completed' && $t->due_date && $t->due_date->betweenIncluded($today, $today->copy()->addDays(7))),
            'Later / no due date' => $myTasks->filter(fn (Task $t) => $t->progress !== 'completed' && ! $t->isOverdue() && (! $t->due_date || $t->due_date->gt($today->copy()->addDays(7)))),
            'Completed' => $myTasks->filter(fn (Task $t) => $t->progress === 'completed'),
        ];

        $plans = TaskPlan::query()
            ->visibleTo($user)
            ->withCount(['tasks', 'tasks as open_tasks_count' => fn ($q) => $q->where('progress', '!=', 'completed')])
            ->with('owner')
            ->orderBy('name')
            ->get();

        return view('tasks.index', ['groups' => $groups, 'plans' => $plans, 'hasEmployee' => $user->employee !== null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', TaskPlan::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $plan = TaskPlan::create($data + ['owner_id' => $request->user()->id]);

        foreach (['To do', 'In progress', 'Done'] as $i => $name) {
            $plan->buckets()->create(['name' => $name, 'position' => $i]);
        }

        return redirect()->route('tasks.plans.show', $plan)->with('status', 'Plan created.');
    }

    public function show(Request $request, TaskPlan $plan): View
    {
        $this->authorize('view', $plan);

        $mine = $request->string('filter')->toString() === 'mine' && $request->user()->employee;

        $plan->load(['buckets.tasks' => fn ($q) => $q
            ->when($mine, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->where('employees.id', $request->user()->employee->id)))
            ->with(['assignees', 'checklistItems'])]);

        return view('tasks.plan', [
            'plan' => $plan,
            'canManage' => $request->user()->can('manage', $plan),
            'mine' => (bool) $mine,
        ]);
    }

    public function update(Request $request, TaskPlan $plan): RedirectResponse
    {
        $this->authorize('manage', $plan);

        $plan->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('status', 'Plan updated.');
    }

    public function destroy(TaskPlan $plan): RedirectResponse
    {
        $this->authorize('manage', $plan);

        $plan->delete();

        return redirect()->route('tasks.index')->with('status', 'Plan deleted.');
    }

    public function storeBucket(Request $request, TaskPlan $plan): RedirectResponse
    {
        $this->authorize('manage', $plan);

        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        $plan->buckets()->create($data + ['position' => ($plan->buckets()->max('position') ?? -1) + 1]);

        return back()->with('status', 'Bucket added.');
    }

    public function updateBucket(Request $request, TaskPlan $plan, TaskBucket $bucket): RedirectResponse
    {
        $this->authorize('manage', $plan);
        abort_unless($bucket->task_plan_id === $plan->id, 404);

        $bucket->update($request->validate(['name' => ['required', 'string', 'max:80']]));

        return back()->with('status', 'Bucket renamed.');
    }

    public function destroyBucket(TaskPlan $plan, TaskBucket $bucket): RedirectResponse
    {
        $this->authorize('manage', $plan);
        abort_unless($bucket->task_plan_id === $plan->id, 404);

        if ($bucket->tasks()->exists()) {
            return back()->withErrors(['bucket' => 'Move or delete the tasks in this bucket first.']);
        }

        $bucket->delete();

        return back()->with('status', 'Bucket deleted.');
    }
}
