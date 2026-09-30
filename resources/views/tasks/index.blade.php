@php
$priorityStyles = ['urgent' => 'bg-danger-100 text-danger-700', 'high' => 'bg-warning-100 text-warning-700', 'medium' => 'bg-accent-100 text-accent-800', 'low' => 'bg-neutral-100 text-neutral-600'];
@endphp

<x-app-layout>
    <x-slot name="header">Tasks</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-danger-50 px-4 py-3 text-sm text-danger-800">{{ $errors->first() }}</div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">My Tasks</h2>

                @if (! $hasEmployee)
                    <p class="rounded-lg border border-neutral-200 bg-white p-6 text-sm text-neutral-500 shadow-md">Your account isn't linked to an employee record, so tasks can't be assigned to you. You can still create and manage plans.</p>
                @elseif (collect($groups)->every(fn ($g) => $g->isEmpty()))
                    <p class="rounded-lg border border-neutral-200 bg-white p-6 text-sm text-neutral-500 shadow-md">Nothing assigned to you yet.</p>
                @else
                    @foreach ($groups as $label => $tasks)
                        @continue($tasks->isEmpty())
                        <div class="rounded-lg border border-neutral-200 bg-white shadow-md">
                            <div class="border-b border-neutral-100 px-4 py-2 text-xs font-semibold uppercase tracking-wide {{ $label === 'Overdue' ? 'text-danger-600' : 'text-neutral-500' }}">{{ $label }} ({{ $tasks->count() }})</div>
                            <ul class="divide-y divide-neutral-100">
                                @foreach ($tasks as $task)
                                    <li class="flex items-center gap-3 px-4 py-2 text-sm">
                                        <form method="POST" action="{{ route('tasks.toggle-complete', $task) }}">
                                            @csrf
                                            <button type="submit" title="{{ $task->progress === 'completed' ? 'Reopen' : 'Mark complete' }}" class="flex h-5 w-5 items-center justify-center rounded-full border {{ $task->progress === 'completed' ? 'border-success-500 bg-success-500 text-white' : 'border-neutral-300 hover:border-success-500' }}">
                                                @if ($task->progress === 'completed')<span class="text-[10px]">&#10003;</span>@endif
                                            </button>
                                        </form>
                                        <a href="{{ route('tasks.show', $task) }}" class="min-w-0 flex-1">
                                            <span class="block truncate font-medium text-neutral-900 {{ $task->progress === 'completed' ? 'line-through text-neutral-400' : '' }}">{{ $task->title }}</span>
                                            <span class="block truncate text-xs text-neutral-500">{{ $task->plan->name }} &middot; {{ $task->bucket->name }}</span>
                                        </a>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $priorityStyles[$task->priority] }}">{{ ucfirst($task->priority) }}</span>
                                        <span class="w-20 text-right text-xs {{ $task->isOverdue() ? 'font-semibold text-danger-600' : 'text-neutral-500' }}">{{ $task->due_date?->format('d M Y') ?? '—' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Plans</h2>

                <div class="space-y-2">
                    @forelse ($plans as $plan)
                        <a href="{{ route('tasks.plans.show', $plan) }}" class="block rounded-lg border border-neutral-200 bg-white px-4 py-3 shadow-sm transition hover:border-brand-400">
                            <span class="block text-sm font-medium text-neutral-900">{{ $plan->name }}</span>
                            <span class="block text-xs text-neutral-500">{{ $plan->open_tasks_count }} open / {{ $plan->tasks_count }} tasks &middot; owner {{ $plan->owner->name }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-neutral-500">No plans yet.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('tasks.plans.store') }}" class="space-y-2 rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
                    @csrf
                    <h3 class="text-sm font-semibold text-neutral-900">New Plan</h3>
                    <input type="text" name="name" required maxlength="120" placeholder="Plan name" class="block w-full rounded-md border-neutral-300 text-sm">
                    <textarea name="description" rows="2" maxlength="1000" placeholder="Description (optional)" class="block w-full rounded-md border-neutral-300 text-sm"></textarea>
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Create Plan</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
