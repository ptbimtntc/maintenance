@php
$statusStyles = [
    'draft' => 'bg-gray-100 text-gray-700',
    'pending_review' => 'bg-amber-100 text-amber-800',
    'active' => 'bg-green-100 text-green-800',
    'archived' => 'bg-gray-100 text-gray-500',
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
                <p class="text-sm text-gray-500">{{ $jobDescriptions->total() }} job description(s), across all positions and versions.</p>
                <x-read-only-badge menu="job-descriptions" />
            </div>

            @can(\App\Enums\PermissionName::ManageJobDescriptions->value)
                <a href="{{ route('job-descriptions.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Add Job Description
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('job-descriptions.index') }}" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3">
            <select name="position_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Positions</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected(($filters['position_id'] ?? null) == $position->id)>{{ $position->title }}</option>
                @endforeach
            </select>

            <select name="status" class="rounded-md border-gray-300 text-sm">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $statusLabels[$status] }}</option>
                @endforeach
            </select>

            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('job-descriptions.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Position</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Job Title</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Version</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Effective Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($jobDescriptions as $jd)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $jd->position->title }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $jd->job_title }}</td>
                            <td class="px-4 py-3 text-gray-600">v{{ $jd->version }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$jd->status] }}">{{ $statusLabels[$jd->status] }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $jd->effective_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('job-descriptions.show', $jd) }}" class="text-slate-600 hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">No job descriptions match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $jobDescriptions->links() }}
    </div>
</x-app-layout>
