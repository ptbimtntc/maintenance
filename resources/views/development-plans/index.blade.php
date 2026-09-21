@php
$statusStyles = ['not_started' => 'bg-neutral-100 text-neutral-600', 'in_progress' => 'bg-accent-100 text-accent-800', 'completed' => 'bg-green-100 text-green-800', 'on_hold' => 'bg-amber-100 text-amber-800'];
@endphp

<x-app-layout>
    <x-slot name="header">Employee Development Plans</x-slot>

    <div class="space-y-4">
        <x-read-only-badge menu="development-plans" />

        @php
            $activeFieldClass = 'border-brand-400 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200';
            $defaultFieldClass = 'border-neutral-300';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-neutral-200 bg-white p-4 sm:grid-cols-3">
                <select name="status" class="rounded-md text-sm {{ filled($filters['status'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                <select name="priority" class="rounded-md text-sm {{ filled($filters['priority'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Priorities</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority }}" @selected(($filters['priority'] ?? null) === $priority)>{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Filter</button>
                    <a href="{{ route('development-plans.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employee</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Objective</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Priority</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Progress</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($plans as $plan)
                        <tr>
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $plan->employee->full_name }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $plan->development_objective }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ ucfirst($plan->priority) }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $plan->progress_percentage }}%</td>
                            <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$plan->status] }}">{{ ucwords(str_replace('_', ' ', $plan->status)) }}</span></td>
                            <td class="px-3 py-2 text-right"><a href="{{ route('employees.show', $plan->employee) }}" class="text-neutral-600 hover:underline">View Employee</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-neutral-500">No development plans yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $plans->links() }}
    </div>
</x-app-layout>
