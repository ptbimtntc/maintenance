@php
$statusStyles = ['not_started' => 'bg-neutral-100 text-neutral-600', 'in_progress' => 'bg-accent-100 text-accent-800', 'completed' => 'bg-success-100 text-success-800', 'on_hold' => 'bg-warning-100 text-warning-800'];
@endphp

<x-app-layout>
    <x-slot name="header">Employee Development Plans</x-slot>

    <div class="space-y-4">
        <x-read-only-badge menu="development-plans" />

        @php
            $activeFieldClass = 'border-brand-300 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200 shadow-sm';
            $defaultFieldClass = 'border-neutral-300 hover:border-accent-400';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-3">
                <x-multi-select-filter name="status" label="All Statuses"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => ucwords(str_replace('_', ' ', $s))])" :selected="$filters['status'] ?? []" />
                <x-multi-select-filter name="priority" label="All Priorities"
                    :options="collect($priorities)->mapWithKeys(fn ($p) => [$p => ucfirst($p)])" :selected="$filters['priority'] ?? []" />
                <div class="flex gap-2">
                    <x-button size="sm" type="submit">Filter</x-button>
                    <x-button variant="secondary" size="sm" href="{{ route('development-plans.index') }}">Reset</x-button>
                </div>
            </form>
        </x-filter-panel>

        <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employee</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Objective</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Priority</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Progress</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2"></th>
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
                            <td class="px-3 py-2 text-right"><a href="{{ route('employees.show', ['employee' => $plan->employee, 'from' => 'development-plans']) }}" class="text-neutral-600 hover:underline">View Employee</a></td>
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
