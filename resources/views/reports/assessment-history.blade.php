<x-app-layout>
    <x-slot name="header">Competency Assessment History</x-slot>

    <div class="space-y-4">
        <a href="{{ route('reports.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Back to Reports</a>

        <form method="GET" class="flex gap-3 rounded-lg border border-gray-200 bg-white p-4">
            <input type="text" name="employee_search" value="{{ $filters['employee_search'] ?? '' }}" placeholder="Search employee..." class="block w-full rounded-md border-gray-300 text-sm" />
            <button type="submit" class="shrink-0 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
            <a href="{{ route('reports.assessment-history') }}" class="shrink-0 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            <a href="{{ route('reports.assessment-history', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="shrink-0 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export XLSX</a>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Skill</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Level</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Assessed By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($assessments as $assessment)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $assessment->employee->full_name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $assessment->skill->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $assessment->competencyLevel->level_number }} — {{ $assessment->competencyLevel->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $assessment->assessment_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $assessment->assessedBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No assessments recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $assessments->links() }}
    </div>
</x-app-layout>
