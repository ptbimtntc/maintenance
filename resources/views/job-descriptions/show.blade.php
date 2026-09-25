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
    <x-slot name="header">Job Description</x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if (session('status'))
                <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-neutral-900">{{ $jobDescription->job_title }}</h2>
                        <p class="text-sm text-neutral-500">{{ $jobDescription->position->title }} &middot; Version {{ $jobDescription->version }}</p>
                    </div>
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$jobDescription->status] }}">{{ $statusLabels[$jobDescription->status] }}</span>
                </div>

                <dl class="mt-6 space-y-4 text-sm">
                    <div><dt class="font-medium text-neutral-500">Job Purpose</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $jobDescription->job_purpose ?: '—' }}</dd></div>
                    <div><dt class="font-medium text-neutral-500">Main Responsibilities</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $jobDescription->main_responsibilities ?: '—' }}</dd></div>
                    <div><dt class="font-medium text-neutral-500">Detailed Duties</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $jobDescription->detailed_duties ?: '—' }}</dd></div>

                    <div class="grid grid-cols-2 gap-4">
                        <div><dt class="font-medium text-neutral-500">Required Education</dt><dd class="mt-1 text-neutral-800">{{ $jobDescription->required_education ?: '—' }}</dd></div>
                        <div><dt class="font-medium text-neutral-500">Required Experience</dt><dd class="mt-1 text-neutral-800">{{ $jobDescription->required_experience ?: '—' }}</dd></div>
                    </div>

                    <div><dt class="font-medium text-neutral-500">Required Technical Skills</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $jobDescription->required_technical_skills ?: '—' }}</dd></div>
                    <div><dt class="font-medium text-neutral-500">Required Soft Skills</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $jobDescription->required_soft_skills ?: '—' }}</dd></div>
                    <div><dt class="font-medium text-neutral-500">Required Certifications</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $jobDescription->required_certifications ?: '—' }}</dd></div>
                    <div><dt class="font-medium text-neutral-500">Safety Responsibilities</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $jobDescription->safety_responsibilities ?: '—' }}</dd></div>

                    <div class="grid grid-cols-2 gap-4">
                        <div><dt class="font-medium text-neutral-500">Reports To</dt><dd class="mt-1 text-neutral-800">{{ $jobDescription->reportsToPosition?->title ?? '—' }}</dd></div>
                        <div><dt class="font-medium text-neutral-500">Direct Reports</dt><dd class="mt-1 text-neutral-800">{{ $jobDescription->direct_reports_summary ?: '—' }}</dd></div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div><dt class="font-medium text-neutral-500">Effective Date</dt><dd class="mt-1 text-neutral-800">{{ $jobDescription->effective_date?->format('d M Y') ?? '—' }}</dd></div>
                        <div><dt class="font-medium text-neutral-500">Review Date</dt><dd class="mt-1 text-neutral-800">{{ $jobDescription->review_date?->format('d M Y') ?? '—' }}</dd></div>
                        <div><dt class="font-medium text-neutral-500">Approved By</dt><dd class="mt-1 text-neutral-800">{{ $jobDescription->approvedBy?->name ?? '—' }}</dd></div>
                    </div>

                    @if ($jobDescription->remarks)
                        <div><dt class="font-medium text-neutral-500">Remarks</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $jobDescription->remarks }}</dd></div>
                    @endif
                </dl>
            </div>

            @can(\App\Enums\PermissionName::ManageJobDescriptions->value)
                <div class="flex flex-wrap gap-2">
                    @if ($jobDescription->status !== 'archived')
                        <a href="{{ route('job-descriptions.edit', $jobDescription) }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Edit</a>
                    @endif

                    @if ($jobDescription->status === 'draft')
                        <form method="POST" action="{{ route('job-descriptions.submit-for-review', $jobDescription) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Submit for Review</button>
                        </form>
                    @endif

                    @if (in_array($jobDescription->status, ['draft', 'pending_review']))
                        <form method="POST" action="{{ route('job-descriptions.approve', $jobDescription) }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-success-600 px-4 py-2 text-sm font-medium text-white hover:bg-success-700">Approve &amp; Activate</button>
                        </form>
                    @endif

                    @if ($jobDescription->status === 'active')
                        <form method="POST" action="{{ route('job-descriptions.archive', $jobDescription) }}" onsubmit="return confirm('Archive this job description?');">
                            @csrf
                            <button type="submit" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Archive</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('job-descriptions.new-revision', $jobDescription) }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Create New Revision</button>
                    </form>
                </div>
            @endcan
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
                <h3 class="text-sm font-semibold text-neutral-900">Version History</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($versions as $version)
                        <li class="flex items-center justify-between">
                            <a href="{{ route('job-descriptions.show', $version) }}" @class(['hover:underline', 'font-semibold text-neutral-900' => $version->id === $jobDescription->id, 'text-neutral-600' => $version->id !== $jobDescription->id])>
                                v{{ $version->version }}
                            </a>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusStyles[$version->status] }}">{{ $statusLabels[$version->status] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <a href="{{ route('job-descriptions.index') }}" class="block text-sm text-neutral-600 hover:underline">&larr; Back to all job descriptions</a>
        </div>
    </div>
</x-app-layout>
