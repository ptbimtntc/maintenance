@php
$statusStyles = ['draft' => 'bg-neutral-100 text-neutral-700', 'active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-neutral-100 text-neutral-500'];
$sessionStatusStyles = ['scheduled' => 'bg-accent-100 text-accent-800', 'ongoing' => 'bg-amber-100 text-amber-800', 'completed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-neutral-100 text-neutral-500'];
@endphp

<x-app-layout>
    <x-slot name="header">Training Program</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg border border-neutral-200 bg-white p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-neutral-900">{{ $program->title }}</h2>
                    <p class="text-sm text-neutral-500">{{ $program->trainingCategory?->name ?? 'Uncategorized' }} &middot; {{ $program->trainingType?->name ?? 'General' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$program->status] }}">{{ ucfirst($program->status) }}</span>
                    @can(\App\Enums\PermissionName::ManageTraining->value)
                        <a href="{{ route('training.programs.edit', $program) }}" class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Edit</a>
                    @endcan
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div><dt class="font-medium text-neutral-500">Target Audience</dt><dd class="mt-1 text-neutral-800">{{ $program->target_audience ?? '—' }}</dd></div>
                <div><dt class="font-medium text-neutral-500">Trainer</dt><dd class="mt-1 text-neutral-800">{{ $program->trainer_name ?? '—' }} {{ $program->is_internal ? '(Internal)' : '(External)' }}</dd></div>
                <div><dt class="font-medium text-neutral-500">Provider</dt><dd class="mt-1 text-neutral-800">{{ $program->trainingProvider?->name ?? '—' }}</dd></div>
                <div><dt class="font-medium text-neutral-500">Location</dt><dd class="mt-1 text-neutral-800">{{ $program->location?->name ?? '—' }}</dd></div>
                <div><dt class="font-medium text-neutral-500">Duration</dt><dd class="mt-1 text-neutral-800">{{ $program->duration_value ? $program->duration_value.' '.$program->duration_unit : '—' }}</dd></div>
                <div><dt class="font-medium text-neutral-500">Estimated Cost</dt><dd class="mt-1 text-neutral-800">{{ $program->estimated_cost ? number_format($program->estimated_cost, 2) : '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="font-medium text-neutral-500">Related Skills</dt><dd class="mt-1 text-neutral-800">{{ $program->skills->pluck('name')->implode(', ') ?: '—' }}</dd></div>
                @if ($program->description)
                    <div class="sm:col-span-2"><dt class="font-medium text-neutral-500">Description</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $program->description }}</dd></div>
                @endif
                @if ($program->objectives)
                    <div class="sm:col-span-2"><dt class="font-medium text-neutral-500">Objectives</dt><dd class="mt-1 whitespace-pre-line text-neutral-800">{{ $program->objectives }}</dd></div>
                @endif
            </dl>
        </div>

        <div>
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Sessions</h3>
                @can(\App\Enums\PermissionName::ManageTraining->value)
                    <a href="{{ route('training.programs.sessions.create', $program) }}" class="text-sm text-neutral-600 hover:underline">+ Add Session</a>
                @endcan
            </div>

            <div class="mt-3 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="border-b-2 border-brand-500 bg-brand-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Dates</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Location</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Participants</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($program->sessions as $session)
                            <tr>
                                <td class="px-3 py-2 text-neutral-800">{{ $session->start_date->format('d M Y') }} &ndash; {{ $session->end_date->format('d M Y') }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ $session->location?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ $session->participants()->count() }}{{ $session->max_participants ? ' / '.$session->max_participants : '' }}</td>
                                <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $sessionStatusStyles[$session->status] }}">{{ ucfirst($session->status) }}</span></td>
                                <td class="px-3 py-2 text-right"><a href="{{ route('training.sessions.show', $session) }}" class="text-neutral-600 hover:underline">Manage</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-neutral-500">No sessions scheduled yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <a href="{{ route('training.programs.index') }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to Training Programs</a>
    </div>
</x-app-layout>
