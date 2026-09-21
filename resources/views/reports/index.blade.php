@php
$sections = [
    'Employees' => [
        ['label' => 'Employee Master List', 'href' => route('employees.index'), 'desc' => 'All employees, exportable to CSV.'],
        ['label' => 'Employee List by Position / Area / Team', 'href' => route('employees.index'), 'desc' => 'Use the filters on the Employees page, then export.'],
    ],
    'Skills & Competency' => [
        ['label' => 'Skill Matrix Report', 'href' => route('skill-matrix.index'), 'desc' => 'Current vs. required competency, filterable.'],
        ['label' => 'Competency Gap Report', 'href' => route('competency-gap-analysis.index'), 'desc' => 'Department-wide gap findings and suggestions.'],
        ['label' => 'Position Competency Requirement Report', 'href' => route('skills.positions.index'), 'desc' => 'Required skills and levels per position.'],
        ['label' => 'Competency Assessment History', 'href' => route('reports.assessment-history'), 'desc' => 'Every assessment ever recorded, across all employees.'],
    ],
    'Training' => [
        ['label' => 'Training Plan Report', 'href' => route('training.programs.index'), 'desc' => 'All training programs and their status.'],
        ['label' => 'Training Schedule Report', 'href' => route('training.calendar'), 'desc' => 'Sessions grouped by month.'],
        ['label' => 'Training Attendance Report', 'href' => route('training.records.index'), 'desc' => 'Attendance and completion per training record.'],
        ['label' => 'Employee Training History', 'href' => route('training.records.index'), 'desc' => 'Search by employee on the Training Records page.'],
        ['label' => 'Training Hours by Employee / Department', 'href' => route('reports.training-hours'), 'desc' => 'Total hours, aggregated two ways.'],
    ],
    'Certificates' => [
        ['label' => 'Certificate Expiry Report', 'href' => route('certificates.index', ['status' => 'expiring_soon']), 'desc' => 'Certificates expiring within 60 days.'],
        ['label' => 'Expired Certificate Report', 'href' => route('certificates.index', ['status' => 'expired']), 'desc' => 'Certificates already past their expiry date.'],
    ],
    'Development' => [
        ['label' => 'Employee Development Plan Report', 'href' => route('development-plans.index'), 'desc' => 'All development plans, filterable by status/priority.'],
        ['label' => 'Employee Development Summary', 'href' => route('reports.development-summary'), 'desc' => 'Plan counts by status, action, and priority.'],
    ],
];
@endphp

@php
$palette = [
    ['bar' => 'bg-brand-500', 'title' => 'text-brand-700', 'hover' => 'hover:border-brand-400 hover:bg-brand-50/40', 'arrow' => 'text-brand-500'],
    ['bar' => 'bg-accent-500', 'title' => 'text-accent-700', 'hover' => 'hover:border-accent-400 hover:bg-accent-50/40', 'arrow' => 'text-accent-500'],
];
@endphp

<x-app-layout>
    <x-slot name="header">Reports</x-slot>

    <div class="space-y-5">
        @foreach ($sections as $section => $reports)
            @php $c = $palette[$loop->index % 2]; @endphp
            <section>
                <div class="mb-2 flex items-center gap-2">
                    <span class="h-4 w-1 rounded-full {{ $c['bar'] }}"></span>
                    <h2 class="text-xs font-semibold uppercase tracking-wide {{ $c['title'] }}">{{ $section }}</h2>
                    <span class="text-xs text-neutral-400">{{ count($reports) }}</span>
                    <div class="h-px flex-1 bg-neutral-200"></div>
                </div>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $report)
                        <a href="{{ $report['href'] }}" class="group flex items-start justify-between gap-2 rounded-md border border-neutral-200 bg-white px-3 py-2 transition {{ $c['hover'] }}">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-neutral-800" title="{{ $report['label'] }}">{{ $report['label'] }}</span>
                                <span class="block truncate text-xs text-neutral-500" title="{{ $report['desc'] }}">{{ $report['desc'] }}</span>
                            </span>
                            <span class="mt-0.5 shrink-0 {{ $c['arrow'] }}">&rarr;</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
