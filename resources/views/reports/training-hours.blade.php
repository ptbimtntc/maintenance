<x-app-layout>
    <x-slot name="header">Training Hours Report</x-slot>

    <div class="space-y-8">
        <a href="{{ route('reports.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Back to Reports</a>

        <div>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">By Employee</h2>
                <a href="{{ route('reports.training-hours', ['export' => 'csv']) }}" class="text-sm text-slate-600 hover:underline">Export CSV</a>
                <a href="{{ route('reports.training-hours', ['export' => 'xlsx']) }}" class="ml-3 text-sm text-slate-600 hover:underline">Export XLSX</a>
            </div>
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Total Hours</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Completed Trainings</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $row->full_name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $row->training_records_sum_duration_hours }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $row->completed_trainings_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No training hours recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">By Department</h2>
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Department</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Total Hours</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($byDepartment as $department => $hours)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $department }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $hours }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-8 text-center text-gray-500">No training hours recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
