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

<x-app-layout>
    <x-slot name="header">Reports</x-slot>

    <div class="space-y-8">
        @foreach ($sections as $section => $reports)
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $section }}</h2>
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($reports as $report)
                        <a href="{{ $report['href'] }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:border-slate-400 hover:shadow-sm">
                            <p class="font-medium text-gray-900">{{ $report['label'] }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $report['desc'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
