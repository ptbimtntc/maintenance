@php
$statusLabels = [
    'meets' => ['label' => 'Meets Requirement', 'class' => 'bg-green-100 text-green-800'],
    'gap' => ['label' => 'Development Required', 'class' => 'bg-red-100 text-red-800'],
    'incomplete' => ['label' => 'Assessment Incomplete', 'class' => 'bg-amber-100 text-amber-800'],
    'no-requirements' => ['label' => 'No Requirements Defined', 'class' => 'bg-gray-100 text-gray-600'],
];
@endphp

<x-app-layout>
    <x-slot name="header">Skill Matrix</x-slot>

    <div class="space-y-4">
        <a href="{{ route('skills.landing') }}" class="text-sm text-slate-600 hover:underline">&larr; Back to Skills &amp; Competencies</a>

        <form method="GET" action="{{ route('skill-matrix.index') }}" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search employee..."
                   class="rounded-md border-gray-300 text-sm" />

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

            <select name="skill_category_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Skill Categories</option>
                @foreach ($skillCategories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['skill_category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>

            <div class="col-span-1 flex gap-2 sm:col-span-2 lg:col-span-5">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('skill-matrix.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="flex flex-wrap gap-3 text-xs">
            @foreach ($statusLabels as $status)
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 {{ $status['class'] }}">{{ $status['label'] }}</span>
            @endforeach
        </div>

        @if ($skills->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                No skills defined yet. Add skills under Skills &amp; Competencies &rarr; Skill Catalog.
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="sticky left-0 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Position</th>
                            @foreach ($skills as $skill)
                                <th class="px-3 py-3 text-center font-medium text-gray-500" title="{{ $skill->name }}">{{ $skill->name }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Overall Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($matrix as $row)
                            <tr>
                                <td class="sticky left-0 bg-white px-4 py-3">
                                    <a href="{{ route('employees.show', $row['employee']) }}" class="font-medium text-slate-900 hover:underline">{{ $row['employee']->full_name }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $row['employee']->position?->title ?? '—' }}</td>
                                @foreach ($skills as $skill)
                                    @php $cell = $row['cells'][$skill->id]; @endphp
                                    <td class="px-3 py-3 text-center">
                                        @if ($cell['required'] === null)
                                            <span class="text-gray-300">—</span>
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
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusLabels[$row['overall_status']]['class'] }}">
                                        {{ $statusLabels[$row['overall_status']]['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $skills->count() + 3 }}" class="px-4 py-10 text-center text-gray-500">No employees match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $employees->links() }}
        @endif
    </div>
</x-app-layout>
