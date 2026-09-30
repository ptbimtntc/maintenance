<x-app-layout>
    <x-slot name="header">Task — {{ $task->title }}</x-slot>

    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-danger-50 px-4 py-3 text-sm text-danger-800">{{ $errors->first() }}</div>
        @endif

        <a href="{{ route('tasks.plans.show', $task->plan) }}" class="text-sm text-neutral-600 hover:underline">&larr; {{ $task->plan->name }}</a>

        <form method="POST" action="{{ route('tasks.update', $task) }}" class="space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-md"
              x-data="{ q: '', selected: @js($task->assignees->pluck('id')->all()), employees: @js($employees) }">
            @csrf @method('PUT')

            <div>
                <x-input-label for="title" value="Title" />
                <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title', $task->title) }}" required />
            </div>

            <div>
                <x-input-label for="description" value="Notes" />
                <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">{{ old('description', $task->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="task_bucket_id" value="Bucket" />
                    <select id="task_bucket_id" name="task_bucket_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                        @foreach ($task->plan->buckets as $bucket)
                            <option value="{{ $bucket->id }}" @selected(old('task_bucket_id', $task->task_bucket_id) == $bucket->id)>{{ $bucket->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="progress" value="Progress" />
                    <select id="progress" name="progress" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                        @foreach (\App\Models\Task::PROGRESS as $p)
                            <option value="{{ $p }}" @selected(old('progress', $task->progress) === $p)>{{ \App\Models\Task::progressLabel($p) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="priority" value="Priority" />
                    <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                        @foreach (\App\Models\Task::PRIORITIES as $p)
                            <option value="{{ $p }}" @selected(old('priority', $task->priority) === $p)>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="start_date" value="Start date" />
                    <x-text-input id="start_date" type="date" name="start_date" class="mt-1 block w-full" value="{{ old('start_date', $task->start_date?->format('Y-m-d')) }}" />
                </div>
                <div>
                    <x-input-label for="due_date" value="Due date" />
                    <x-text-input id="due_date" type="date" name="due_date" class="mt-1 block w-full" value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}" />
                </div>
            </div>

            <div>
                <div class="flex items-center gap-3">
                    <x-input-label value="Assign to" />
                    <div class="flex -space-x-2">
                        @foreach ($task->assignees as $assignee)
                            <div x-data="{ open: false }" class="group relative" @click.outside="open = false">
                                <button type="button" @click="open = !open" aria-label="{{ $assignee->full_name }}" class="block rounded-full ring-2 ring-white">
                                    @if ($assignee->photo_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($assignee->photo_path) }}" alt="{{ $assignee->full_name }}" class="h-8 w-8 rounded-full object-cover">
                                    @else
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ strtoupper(mb_substr($assignee->full_name, 0, 1)) }}</span>
                                    @endif
                                </button>
                                <span x-show="open" x-cloak class="absolute left-1/2 top-full z-10 mt-1 -translate-x-1/2 whitespace-nowrap rounded bg-neutral-900 px-2 py-1 text-xs text-white shadow group-hover:block" :class="open ? '' : 'hidden'">{{ $assignee->full_name }}</span>
                                <span x-show="!open" class="pointer-events-none absolute left-1/2 top-full z-10 mt-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-neutral-900 px-2 py-1 text-xs text-white shadow group-hover:block">{{ $assignee->full_name }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <input type="text" x-model="q" placeholder="Search employees…" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                <div class="mt-2 max-h-48 divide-y divide-neutral-100 overflow-y-auto rounded-md border border-neutral-200">
                    <template x-for="e in employees.filter(e => (e.full_name + ' ' + e.employee_number).toLowerCase().includes(q.toLowerCase()))" :key="e.id">
                        <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-neutral-50">
                            <input type="checkbox" name="assignee_ids[]" :value="e.id" x-model.number="selected" class="rounded border-neutral-300">
                            <span x-text="e.full_name"></span>
                            <span class="text-xs text-neutral-400" x-text="e.employee_number"></span>
                        </label>
                    </template>
                </div>
                <p class="mt-1 text-xs text-neutral-500" x-text="selected.length + ' assigned'"></p>
            </div>

            <div class="flex items-center justify-between">
                <p class="text-xs text-neutral-400">Created by {{ $task->creator->name }} on {{ $task->created_at->format('d M Y') }}@if ($task->completed_at) &middot; completed {{ $task->completed_at->format('d M Y') }}@endif</p>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Save</button>
            </div>
        </form>

        <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
            <h3 class="text-sm font-semibold text-neutral-900">Checklist ({{ $task->checklistItems->where('is_done', true)->count() }}/{{ $task->checklistItems->count() }})</h3>
            <ul class="mt-3 space-y-1">
                @foreach ($task->checklistItems as $item)
                    <li class="flex items-center gap-2 text-sm">
                        <form method="POST" action="{{ route('tasks.checklist.toggle', [$task, $item]) }}">
                            @csrf
                            <button type="submit" class="flex h-4 w-4 items-center justify-center rounded border {{ $item->is_done ? 'border-success-500 bg-success-500 text-white' : 'border-neutral-300' }}">@if ($item->is_done)<span class="text-[9px]">&#10003;</span>@endif</button>
                        </form>
                        <span class="flex-1 {{ $item->is_done ? 'line-through text-neutral-400' : 'text-neutral-800' }}">{{ $item->title }}</span>
                        <form method="POST" action="{{ route('tasks.checklist.destroy', [$task, $item]) }}">
                            @csrf @method('DELETE')
                            <button class="text-xs text-neutral-400 hover:text-danger-600">Remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('tasks.checklist.store', $task) }}" class="mt-3 flex gap-2">
                @csrf
                <input type="text" name="title" required maxlength="200" placeholder="Add an item" class="block w-full rounded-md border-neutral-300 text-sm">
                <button type="submit" class="shrink-0 rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 hover:bg-neutral-50">Add</button>
            </form>
        </div>

        <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task?');">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-danger-600 hover:underline">Delete task</button>
        </form>
    </div>
</x-app-layout>
