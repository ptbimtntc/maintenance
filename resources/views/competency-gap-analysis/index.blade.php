<x-app-layout>
    <x-slot name="header">Competency Gap Analysis</x-slot>

    <div class="space-y-8">
        @php
            $activeFieldClass = 'border-brand-400 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200';
            $defaultFieldClass = 'border-neutral-300';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" action="{{ route('competency-gap-analysis.index') }}" class="grid grid-cols-1 gap-3 rounded-lg border border-neutral-200 bg-white p-4 sm:grid-cols-4">
                <select name="maintenance_area_id" class="rounded-md text-sm {{ filled($filters['maintenance_area_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Areas</option>
                    @foreach ($maintenanceAreas as $area)
                        <option value="{{ $area->id }}" @selected(($filters['maintenance_area_id'] ?? null) == $area->id)>{{ $area->name }}</option>
                    @endforeach
                </select>

                <select name="maintenance_team_id" class="rounded-md text-sm {{ filled($filters['maintenance_team_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Teams</option>
                    @foreach ($maintenanceTeams as $team)
                        <option value="{{ $team->id }}" @selected(($filters['maintenance_team_id'] ?? null) == $team->id)>{{ $team->name }}</option>
                    @endforeach
                </select>

                <select name="position_id" class="rounded-md text-sm {{ filled($filters['position_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Positions</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected(($filters['position_id'] ?? null) == $position->id)>{{ $position->title }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Filter</button>
                    <a href="{{ route('competency-gap-analysis.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-dashboard-stat label="Employees with Gaps" :value="$summary['employees_with_gaps']" />
            <x-dashboard-stat label="Employees with Incomplete Assessments" :value="$summary['employees_with_incomplete_assessments']" />
            <x-dashboard-stat label="Skills Needing Attention" :value="$summary['skills_needing_attention']" />
            <x-dashboard-stat label="Employees Evaluated" :value="$summary['total_employees_evaluated']" />
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Skills with the Largest Gaps</h2>
            <div class="mt-3 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="border-b-2 border-brand-500 bg-brand-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Skill</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employees Required</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">With Gap</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Not Assessed</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Avg. Gap</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($skillBreakdown as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium text-neutral-900">{{ $row['skill']->name }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ $row['employees_required'] }}</td>
                                <td class="px-3 py-2">
                                    @if ($row['employees_with_gap'] > 0)
                                        <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800">{{ $row['employees_with_gap'] }}</span>
                                    @else
                                        <span class="text-neutral-400">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-neutral-600">{{ $row['employees_incomplete'] }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ $row['average_gap'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-neutral-500">No skill requirements defined yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Positions with the Largest Gaps</h2>
                <div class="mt-3 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="border-b-2 border-brand-500 bg-brand-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Position</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employees</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">With Gap</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($positionBreakdown as $row)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $row['position']->title }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $row['employee_count'] }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $row['employees_with_gap'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-neutral-500">No data available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Areas Requiring Development</h2>
                <div class="mt-3 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="border-b-2 border-brand-500 bg-brand-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Maintenance Area</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employees</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">With Gap</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($areaBreakdown as $row)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $row['area']->name }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $row['employee_count'] }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $row['employees_with_gap'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-neutral-500">No data available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Employees with Competency Gaps</h2>
            <div class="mt-3 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="border-b-2 border-brand-500 bg-brand-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employee</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Position</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Skills Below Requirement</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($employeesWithGaps as $summary)
                            <tr>
                                <td class="px-3 py-2 font-medium text-neutral-900">{{ $summary['employee']->full_name }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ $summary['employee']->position?->title ?? '—' }}</td>
                                <td class="px-3 py-2 text-neutral-600">
                                    {{ $summary['rows']->where('status', 'gap')->pluck('skill.name')->implode(', ') }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ route('employees.show', $summary['employee']) }}" class="text-neutral-600 hover:underline">View Profile</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-neutral-500">No employees currently have a competency gap. 🎉</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Training Recommendations</h2>
            <p class="mt-1 text-sm text-neutral-500">
                System-generated suggestions based on current gaps — not an approved training plan. Review and decide via the Training Management module.
            </p>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($trainingRecommendations as $row)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Suggested</p>
                        <p class="mt-1 font-medium text-neutral-900">{{ $row['skill']->name }}</p>
                        <p class="mt-1 text-sm text-neutral-600">{{ $row['employees_with_gap'] }} employee(s) currently below the required level.</p>
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">No training recommendations at this time.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
