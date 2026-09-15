@php
$completionStyles = ['completed' => 'bg-green-100 text-green-800', 'incomplete' => 'bg-amber-100 text-amber-800', 'failed' => 'bg-red-100 text-red-800'];
@endphp

<x-app-layout>
    <x-slot name="header">Training Records</x-slot>

    <div class="space-y-4">
        <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3">
            <input type="text" name="employee_search" value="{{ $filters['employee_search'] ?? '' }}" placeholder="Search employee..." class="rounded-md border-gray-300 text-sm" />
            <select name="completion_status" class="rounded-md border-gray-300 text-sm">
                <option value="">All Completion Statuses</option>
                @foreach ($completionStatuses as $status)
                    <option value="{{ $status }}" @selected(($filters['completion_status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('training.records.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                <a href="{{ route('training.records.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Program</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Duration (hrs)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Completion</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($records as $record)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $record->employee->full_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $record->trainingProgram?->title ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $record->training_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $record->duration_hours ?? '—' }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $completionStyles[$record->completion_status] }}">{{ ucfirst($record->completion_status) }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('employees.show', $record->employee) }}" class="text-slate-600 hover:underline">View Employee</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No training records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $records->links() }}
    </div>
</x-app-layout>
