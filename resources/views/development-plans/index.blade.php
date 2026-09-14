@php
$statusStyles = ['not_started' => 'bg-gray-100 text-gray-600', 'in_progress' => 'bg-blue-100 text-blue-800', 'completed' => 'bg-green-100 text-green-800', 'on_hold' => 'bg-amber-100 text-amber-800'];
@endphp

<x-app-layout>
    <x-slot name="header">Employee Development Plans</x-slot>

    <div class="space-y-4">
        <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3">
            <select name="status" class="rounded-md border-gray-300 text-sm">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
            <select name="priority" class="rounded-md border-gray-300 text-sm">
                <option value="">All Priorities</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority }}" @selected(($filters['priority'] ?? null) === $priority)>{{ ucfirst($priority) }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('development-plans.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Objective</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Priority</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Progress</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($plans as $plan)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $plan->employee->full_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $plan->development_objective }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ ucfirst($plan->priority) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $plan->progress_percentage }}%</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$plan->status] }}">{{ ucwords(str_replace('_', ' ', $plan->status)) }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('employees.show', $plan->employee) }}" class="text-slate-600 hover:underline">View Employee</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No development plans yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $plans->links() }}
    </div>
</x-app-layout>
