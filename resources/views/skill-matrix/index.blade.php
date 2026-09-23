@php
$statusLabels = [
    'meets' => ['label' => 'Meets Requirement', 'class' => 'bg-green-100 text-green-800'],
    'gap' => ['label' => 'Development Required', 'class' => 'bg-red-100 text-red-800'],
    'incomplete' => ['label' => 'Assessment Incomplete', 'class' => 'bg-amber-100 text-amber-800'],
    'no-requirements' => ['label' => 'No Requirements Defined', 'class' => 'bg-neutral-100 text-neutral-600'],
];
@endphp

<x-app-layout>
    <x-slot name="header">Skill Matrix</x-slot>

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('skills.landing') }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to Skills &amp; Competencies</a>
            <a href="{{ route('skill-matrix.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                Export XLSX
            </a>
        </div>

        @php
            $activeFieldClass = 'border-brand-300 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200 shadow-sm';
            $defaultFieldClass = 'border-neutral-300 hover:border-accent-400';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" action="{{ route('skill-matrix.index') }}" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search employee..."
                       class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['search'] ?? null) ? $activeFieldClass : $defaultFieldClass }}" />

                <select name="maintenance_area_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['maintenance_area_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Areas</option>
                    @foreach ($maintenanceAreas as $area)
                        <option value="{{ $area->id }}" @selected(($filters['maintenance_area_id'] ?? null) == $area->id)>{{ $area->name }}</option>
                    @endforeach
                </select>

                <select name="maintenance_team_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['maintenance_team_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Teams</option>
                    @foreach ($maintenanceTeams as $team)
                        <option value="{{ $team->id }}" @selected(($filters['maintenance_team_id'] ?? null) == $team->id)>{{ $team->name }}</option>
                    @endforeach
                </select>

                <select name="position_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['position_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Positions</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected(($filters['position_id'] ?? null) == $position->id)>{{ $position->title }}</option>
                    @endforeach
                </select>

                <select name="skill_category_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['skill_category_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Skill Categories</option>
                    @foreach ($skillCategories as $category)
                        <option value="{{ $category->id }}" @selected(($filters['skill_category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>

                <div class="col-span-1 flex gap-2 sm:col-span-2 lg:col-span-5">
                    <button type="submit" class="rounded-md bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Filter</button>
                    <a href="{{ route('skill-matrix.index') }}" class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 transition hover:border-accent-400 hover:bg-accent-50 hover:text-accent-700">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="flex flex-wrap gap-3 text-xs">
            @foreach ($statusLabels as $status)
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 {{ $status['class'] }}">{{ $status['label'] }}</span>
            @endforeach
        </div>

        @if ($skills->isEmpty())
            <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center text-sm text-neutral-500">
                No skills defined yet. Add skills under Skills &amp; Competencies &rarr; Skill Catalog.
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="border-b-2 border-brand-500 bg-brand-50">
                        <tr>
                            <th class="sticky left-0 z-10 w-28 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">NIK</th>
                            <th class="sticky left-28 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employee</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Position</th>
                            @foreach ($skills as $skill)
                                <th class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-brand-700" title="{{ $skill->name }}">{{ $skill->name }}</th>
                            @endforeach
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Overall Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($matrix as $row)
                            <tr>
                                <td class="sticky left-0 z-10 w-28 bg-white px-3 py-2 text-neutral-600">{{ $row['employee']->employee_number ?? '—' }}</td>
                                <td class="sticky left-28 z-10 bg-white px-3 py-2">
                                    <a href="{{ route('employees.show', $row['employee']) }}" class="font-medium text-neutral-900 hover:underline">{{ $row['employee']->full_name }}</a>
                                </td>
                                <td class="px-3 py-2 text-neutral-600">{{ $row['employee']->position?->title ?? '—' }}</td>
                                @foreach ($skills as $skill)
                                    @php $cell = $row['cells'][$skill->id]; @endphp
                                    <td class="px-3 py-2 text-center">
                                        @if ($cell['required'] === null)
                                            <span class="text-neutral-300">—</span>
                                        @elseif ($cell['current'] === null)
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800" title="Required: {{ $cell['required']->name }}, not yet assessed">N/A</span>
                                        @else
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-green-100 text-green-800' => $cell['gap'] <= 0,
                                                'bg-red-100 text-red-800' => $cell['gap'] > 0,
                                            ])" title="Current: {{ $cell['current']->name }} / Required: {{ $cell['required']->name }}">
                                                {{ $cell['current']->level_number }}/{{ $cell['required']->level_number }}
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusLabels[$row['overall_status']]['class'] }}">
                                        {{ $statusLabels[$row['overall_status']]['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $skills->count() + 4 }}" class="px-4 py-10 text-center text-neutral-500">No employees match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $employees->links() }}
        @endif
    </div>
</x-app-layout>
