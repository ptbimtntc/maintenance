@php
$priorityStyles = ['urgent' => 'bg-danger-100 text-danger-700', 'high' => 'bg-warning-100 text-warning-700', 'medium' => 'bg-accent-100 text-accent-800', 'low' => 'bg-neutral-100 text-neutral-600'];
@endphp

<x-app-layout>
    <x-slot name="header">{{ $plan->name }}</x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-danger-50 px-4 py-3 text-sm text-danger-800">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('tasks.index') }}" class="text-sm text-neutral-600 hover:underline">&larr; All plans</a>
                @if ($plan->description)<p class="mt-1 text-sm text-neutral-500">{{ $plan->description }}</p>@endif
            </div>
            <div class="flex items-center gap-2 text-sm">
                <a href="{{ route('tasks.plans.show', $plan) }}" class="rounded-md border px-3 py-1.5 {{ ! $mine ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-neutral-300 text-neutral-600 hover:bg-neutral-50' }}">All tasks</a>
                <a href="{{ route('tasks.plans.show', [$plan, 'filter' => 'mine']) }}" class="rounded-md border px-3 py-1.5 {{ $mine ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-neutral-300 text-neutral-600 hover:bg-neutral-50' }}">Assigned to me</a>
                @if ($canManage)
                    <form method="POST" action="{{ route('tasks.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this plan and all its tasks?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-md border border-danger-300 px-3 py-1.5 text-danger-700 hover:bg-danger-50">Delete plan</button>
                    </form>
                @endif
            </div>
        </div>

        <div id="task-board" data-move-url-template="{{ route('tasks.move', ['task' => '__ID__']) }}" class="flex items-start gap-4 overflow-x-auto pb-4">
            @foreach ($plan->buckets as $bucket)
                <div class="w-72 shrink-0 rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                    <div class="mb-3 flex items-center justify-between gap-2" x-data="{ editing: false }">
                        <h3 x-show="!editing" class="truncate text-sm font-semibold text-neutral-800">{{ $bucket->name }} <span class="font-normal text-neutral-400">{{ $bucket->tasks->count() }}</span></h3>
                        @if ($canManage)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('tasks.plans.buckets.update', [$plan, $bucket]) }}" class="flex flex-1 gap-1">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $bucket->name }}" required maxlength="80" class="min-w-0 flex-1 rounded-md border-neutral-300 py-1 text-sm">
                                <button class="text-xs text-brand-700">Save</button>
                            </form>
                            <div x-show="!editing" class="flex gap-2 text-xs text-neutral-400">
                                <button type="button" @click="editing = true" class="hover:text-neutral-700">Rename</button>
                                <form method="POST" action="{{ route('tasks.plans.buckets.destroy', [$plan, $bucket]) }}" onsubmit="return confirm('Delete this bucket?');">
                                    @csrf @method('DELETE')
                                    <button class="hover:text-danger-600">Delete</button>
                                </form>
                            </div>
                        @endif
                    </div>

                    <div data-bucket-drop="{{ $bucket->id }}" class="min-h-[2rem] space-y-2">
                        @foreach ($bucket->tasks as $task)
                            @php $done = $task->checklistItems->where('is_done', true)->count(); @endphp
                            <div draggable="true" data-task-id="{{ $task->id }}" class="cursor-grab rounded-md border border-neutral-200 bg-white p-3 shadow-sm">
                                <div class="flex items-start gap-2">
                                    <form method="POST" action="{{ route('tasks.toggle-complete', $task) }}">
                                        @csrf
                                        <button type="submit" title="{{ $task->progress === 'completed' ? 'Reopen' : 'Mark complete' }}" class="mt-0.5 flex h-4 w-4 items-center justify-center rounded-full border {{ $task->progress === 'completed' ? 'border-success-500 bg-success-500 text-white' : 'border-neutral-300 hover:border-success-500' }}">
                                            @if ($task->progress === 'completed')<span class="text-[9px]">&#10003;</span>@endif
                                        </button>
                                    </form>
                                    <a href="{{ route('tasks.show', $task) }}" class="min-w-0 flex-1 text-sm font-medium text-neutral-900 hover:underline {{ $task->progress === 'completed' ? 'line-through text-neutral-400' : '' }}">{{ $task->title }}</a>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                                    <span class="rounded-full px-2 py-0.5 font-medium {{ $priorityStyles[$task->priority] }}">{{ ucfirst($task->priority) }}</span>
                                    @if ($task->due_date)
                                        <span class="{{ $task->isOverdue() ? 'font-semibold text-danger-600' : 'text-neutral-500' }}">{{ $task->due_date->format('d M') }}</span>
                                    @endif
                                    @if ($task->checklistItems->isNotEmpty())
                                        <span class="text-neutral-500">&#9745; {{ $done }}/{{ $task->checklistItems->count() }}</span>
                                    @endif
                                    <span class="ml-auto flex -space-x-1">
                                        @foreach ($task->assignees->take(3) as $a)
                                            <span title="{{ $a->full_name }}" class="flex h-5 w-5 items-center justify-center rounded-full border border-white bg-brand-100 text-[9px] font-semibold text-brand-700">{{ strtoupper(mb_substr($a->full_name, 0, 1)) }}</span>
                                        @endforeach
                                        @if ($task->assignees->count() > 3)
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full border border-white bg-neutral-200 text-[9px] text-neutral-600">+{{ $task->assignees->count() - 3 }}</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('tasks.store', $plan) }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="task_bucket_id" value="{{ $bucket->id }}">
                        <input type="text" name="title" required maxlength="200" placeholder="+ Add task" class="block w-full rounded-md border-neutral-300 bg-white text-sm">
                    </form>
                </div>
            @endforeach

            @if ($canManage)
                <form method="POST" action="{{ route('tasks.plans.buckets.store', $plan) }}" class="w-72 shrink-0 rounded-lg border border-dashed border-neutral-300 p-3">
                    @csrf
                    <input type="text" name="name" required maxlength="80" placeholder="+ Add bucket" class="block w-full rounded-md border-neutral-300 text-sm">
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
