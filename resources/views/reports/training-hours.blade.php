<x-app-layout>
    <x-slot name="header">Training Hours Report</x-slot>

    <div class="space-y-8">
        <a href="{{ route('reports.index') }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to Reports</a>

        <div>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">By Employee</h2>
                <a href="{{ route('reports.training-hours', ['export' => 'xlsx']) }}" class="text-sm text-neutral-600 hover:underline">Export XLSX</a>
            </div>
            <div class="mt-3 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-neutral-500">Employee</th>
                            <th class="px-4 py-3 text-left font-medium text-neutral-500">Total Hours</th>
                            <th class="px-4 py-3 text-left font-medium text-neutral-500">Completed Trainings</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $row->full_name }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $row->training_records_sum_duration_hours }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $row->completed_trainings_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-neutral-500">No training hours recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">By Department</h2>
            <div class="mt-3 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-neutral-500">Department</th>
                            <th class="px-4 py-3 text-left font-medium text-neutral-500">Total Hours</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($byDepartment as $department => $hours)
                            <tr>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $department }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $hours }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-8 text-center text-neutral-500">No training hours recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
