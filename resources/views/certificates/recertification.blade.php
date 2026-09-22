@php
$windowLabels = [
    '30' => 'Due ≤ 30 days (incl. overdue)',
    '60' => 'Due ≤ 60 days (incl. overdue)',
    '90' => 'Due ≤ 90 days (incl. overdue)',
    'expired' => 'Already overdue',
    'all' => 'All certificates with an expiry date',
];
@endphp

<x-app-layout>
    <x-slot name="header">Recertification</x-slot>

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-red-700">Overdue</p>
                <p class="mt-1 text-2xl font-semibold text-red-800">{{ $summary['overdue'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Due ≤ 30 days</p>
                <p class="mt-1 text-2xl font-semibold text-amber-800">{{ $summary['due_30'] }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Due ≤ 60 days</p>
                <p class="mt-1 text-2xl font-semibold text-neutral-800">{{ $summary['due_60'] }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Due ≤ 90 days</p>
                <p class="mt-1 text-2xl font-semibold text-neutral-800">{{ $summary['due_90'] }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-1 rounded-lg border border-neutral-200 bg-white p-1">
                @foreach ($windowLabels as $value => $label)
                    <a href="{{ route('certificates.recertification', array_merge(request()->except('page'), ['window' => $value])) }}"
                       @class([
                           'rounded-md px-3 py-1.5 text-xs font-medium',
                           'bg-brand-600 text-white' => $window === $value,
                           'text-neutral-600 hover:bg-neutral-50' => $window !== $value,
                       ])>
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <a href="{{ route('certificates.recertification', array_merge(request()->query(), ['export' => 'xlsx'])) }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                Export XLSX
            </a>
        </div>

        @php
            $activeFieldClass = 'border-brand-400 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200';
            $defaultFieldClass = 'border-neutral-300';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-neutral-200 bg-white p-4 sm:grid-cols-4">
                <input type="hidden" name="window" value="{{ $window }}">
                <input type="text" name="employee_search" value="{{ $filters['employee_search'] ?? '' }}" placeholder="Search employee..." class="rounded-md text-sm {{ filled($filters['employee_search'] ?? null) ? $activeFieldClass : $defaultFieldClass }}" />
                <select name="maintenance_team_id" class="rounded-md text-sm {{ filled($filters['maintenance_team_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Teams</option>
                    @foreach ($maintenanceTeams as $team)
                        <option value="{{ $team->id }}" @selected(($filters['maintenance_team_id'] ?? null) == $team->id)>{{ $team->name }}</option>
                    @endforeach
                </select>
                <select name="certificate_type_id" class="rounded-md text-sm {{ filled($filters['certificate_type_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Types</option>
                    @foreach ($certificateTypes as $type)
                        <option value="{{ $type->id }}" @selected(($filters['certificate_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Filter</button>
                    <a href="{{ route('certificates.recertification', ['window' => $window]) }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employee</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Team</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Certificate</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Expiry Date</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Days Remaining</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($certificates as $certificate)
                        @php $daysRemaining = (int) now()->startOfDay()->diffInDays($certificate->expiry_date, false); @endphp
                        <tr>
                            <td class="px-3 py-2 font-medium text-neutral-900">
                                {{ $certificate->employee->full_name }}
                                <span class="block text-xs font-normal text-neutral-500">{{ $certificate->employee->employee_number }}</span>
                            </td>
                            <td class="px-3 py-2 text-neutral-600">{{ $certificate->employee->maintenanceTeam?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-neutral-700">
                                {{ $certificate->name }}
                                <span class="block text-xs font-normal text-neutral-500">{{ $certificate->certificateType?->name ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-2 text-neutral-600">{{ $certificate->expiry_date?->format('d M Y') }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                    'bg-red-100 text-red-800' => $daysRemaining < 0,
                                    'bg-amber-100 text-amber-800' => $daysRemaining >= 0 && $daysRemaining <= 30,
                                    'bg-neutral-100 text-neutral-600' => $daysRemaining > 30,
                                ])>
                                    {{ $daysRemaining < 0 ? abs($daysRemaining).' days overdue' : $daysRemaining.' days left' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('certificates.show', $certificate) }}" target="_blank" class="font-medium text-brand-700 hover:underline">View Certificate</a>
                                <a href="{{ route('employees.show', $certificate->employee) }}" class="ml-3 text-neutral-600 hover:underline">View Employee</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-neutral-500">Nothing due for recertification in this window.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $certificates->links() }}
    </div>
</x-app-layout>
