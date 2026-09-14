<x-app-layout>
    <x-slot name="header">Competency Gap Analysis</x-slot>

    <div class="space-y-8">
        <form method="GET" action="{{ route('competency-gap-analysis.index') }}" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-4">
            <select name="maintenance_area_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Areas</option>
                @foreach ($maintenanceAreas as $area)
                    <option value="{{ $area->id }}" @selected(($filters['maintenance_area_id'] ?? null) == $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>

            <select name="maintenance_team_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Teams</option>
                @foreach ($maintenanceTeams as $team)
                    <option value="{{ $team->id }}" @selected(($filters['maintenance_team_id'] ?? null) == $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>

            <select name="position_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Positions</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected(($filters['position_id'] ?? null) == $position->id)>{{ $position->title }}</option>
                @endforeach
            </select>

            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('competency-gap-analysis.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-dashboard-stat label="Employees with Gaps" :value="$summary['employees_with_gaps']" />
            <x-dashboard-stat label="Employees with Incomplete Assessments" :value="$summary['employees_with_incomplete_assessments']" />
            <x-dashboard-stat label="Skills Needing Attention" :value="$summary['skills_needing_attention']" />
            <x-dashboard-stat label="Employees Evaluated" :value="$summary['total_employees_evaluated']" />
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Skills with the Largest Gaps</h2>
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Skill</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Employees Required</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">With Gap</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Not Assessed</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Avg. Gap</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($skillBreakdown as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $row['skill']->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $row['employees_required'] }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['employees_with_gap'] > 0)
                                        <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800">{{ $row['employees_with_gap'] }}</span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $row['employees_incomplete'] }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $row['average_gap'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No skill requirements defined yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Positions with the Largest Gaps</h2>
                <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Position</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Employees</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">With Gap</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($positionBreakdown as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row['position']->title }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $row['employee_count'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $row['employees_with_gap'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No data available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Areas Requiring Development</h2>
                <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Maintenance Area</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Employees</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">With Gap</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($areaBreakdown as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row['area']->name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $row['employee_count'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $row['employees_with_gap'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No data available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Employees with Competency Gaps</h2>
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Position</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Skills Below Requirement</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($employeesWithGaps as $summary)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $summary['employee']->full_name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $summary['employee']->position?->title ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $summary['rows']->where('status', 'gap')->pluck('skill.name')->implode(', ') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('employees.show', $summary['employee']) }}" class="text-slate-600 hover:underline">View Profile</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No employees currently have a competency gap. 🎉</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Training Recommendations</h2>
            <p class="mt-1 text-sm text-gray-500">
                System-generated suggestions based on current gaps — not an approved training plan. Review and decide via the Training Management module.
            </p>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($trainingRecommendations as $row)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Suggested</p>
                        <p class="mt-1 font-medium text-gray-900">{{ $row['skill']->name }}</p>
                        <p class="mt-1 text-sm text-gray-600">{{ $row['employees_with_gap'] }} employee(s) currently below the required level.</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No training recommendations at this time.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
