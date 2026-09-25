@php
$statusStyles = [
    'draft' => 'bg-neutral-100 text-neutral-700',
    'pending_review' => 'bg-warning-100 text-warning-800',
    'active' => 'bg-success-100 text-success-800',
    'archived' => 'bg-neutral-100 text-neutral-500',
];
$statusLabels = [
    'draft' => 'Draft',
    'pending_review' => 'Pending Review',
    'active' => 'Active',
    'archived' => 'Archived',
];
@endphp

<x-app-layout>
    <x-slot name="header">Job Descriptions</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <p class="text-sm text-neutral-500">{{ $jobDescriptions->total() }} job description(s), across all positions and versions.</p>
                <x-read-only-badge menu="job-descriptions" />
            </div>

            @can(\App\Enums\PermissionName::ManageJobDescriptions->value)
                <a href="{{ route('job-descriptions.create') }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Add Job Description
                </a>
            @endcan
        </div>

        @php
            $activeFieldClass = 'border-brand-300 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200 shadow-sm';
            $defaultFieldClass = 'border-neutral-300 hover:border-accent-400';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" action="{{ route('job-descriptions.index') }}" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-3">
                <select name="position_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['position_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Positions</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected(($filters['position_id'] ?? null) == $position->id)>{{ $position->title }}</option>
                    @endforeach
                </select>

                <select name="status" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['status'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $statusLabels[$status] }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Filter</button>
                    <a href="{{ route('job-descriptions.index') }}" class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 transition hover:border-accent-400 hover:bg-accent-50 hover:text-accent-700">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Position</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Job Title</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Version</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Effective Date</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($jobDescriptions as $jd)
                        <tr>
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $jd->position->title }}</td>
                            <td class="px-3 py-2 text-neutral-700">{{ $jd->job_title }}</td>
                            <td class="px-3 py-2 text-neutral-600">v{{ $jd->version }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$jd->status] }}">{{ $statusLabels[$jd->status] }}</span>
                            </td>
                            <td class="px-3 py-2 text-neutral-600">{{ $jd->effective_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('job-descriptions.show', $jd) }}" class="text-neutral-600 hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">No job descriptions match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $jobDescriptions->links() }}
    </div>
</x-app-layout>
