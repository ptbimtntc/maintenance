@php
$statusStyles = ['draft' => 'bg-gray-100 text-gray-700', 'active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-500'];
$sessionStatusStyles = ['scheduled' => 'bg-blue-100 text-blue-800', 'ongoing' => 'bg-amber-100 text-amber-800', 'completed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-gray-100 text-gray-500'];
@endphp

<x-app-layout>
    <x-slot name="header">Training Program</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ $program->title }}</h2>
                    <p class="text-sm text-gray-500">{{ $program->trainingCategory?->name ?? 'Uncategorized' }} &middot; {{ $program->trainingType?->name ?? 'General' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$program->status] }}">{{ ucfirst($program->status) }}</span>
                    @can(\App\Enums\PermissionName::ManageTraining->value)
                        <a href="{{ route('training.programs.edit', $program) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                    @endcan
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div><dt class="font-medium text-gray-500">Target Audience</dt><dd class="mt-1 text-gray-800">{{ $program->target_audience ?? '—' }}</dd></div>
                <div><dt class="font-medium text-gray-500">Trainer</dt><dd class="mt-1 text-gray-800">{{ $program->trainer_name ?? '—' }} {{ $program->is_internal ? '(Internal)' : '(External)' }}</dd></div>
                <div><dt class="font-medium text-gray-500">Provider</dt><dd class="mt-1 text-gray-800">{{ $program->trainingProvider?->name ?? '—' }}</dd></div>
                <div><dt class="font-medium text-gray-500">Location</dt><dd class="mt-1 text-gray-800">{{ $program->location?->name ?? '—' }}</dd></div>
                <div><dt class="font-medium text-gray-500">Duration</dt><dd class="mt-1 text-gray-800">{{ $program->duration_value ? $program->duration_value.' '.$program->duration_unit : '—' }}</dd></div>
                <div><dt class="font-medium text-gray-500">Estimated Cost</dt><dd class="mt-1 text-gray-800">{{ $program->estimated_cost ? number_format($program->estimated_cost, 2) : '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="font-medium text-gray-500">Related Skills</dt><dd class="mt-1 text-gray-800">{{ $program->skills->pluck('name')->implode(', ') ?: '—' }}</dd></div>
                @if ($program->description)
                    <div class="sm:col-span-2"><dt class="font-medium text-gray-500">Description</dt><dd class="mt-1 whitespace-pre-line text-gray-800">{{ $program->description }}</dd></div>
                @endif
                @if ($program->objectives)
                    <div class="sm:col-span-2"><dt class="font-medium text-gray-500">Objectives</dt><dd class="mt-1 whitespace-pre-line text-gray-800">{{ $program->objectives }}</dd></div>
                @endif
            </dl>
        </div>

        <div>
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Sessions</h3>
                @can(\App\Enums\PermissionName::ManageTraining->value)
                    <a href="{{ route('training.programs.sessions.create', $program) }}" class="text-sm text-slate-600 hover:underline">+ Add Session</a>
                @endcan
            </div>

            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Dates</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Location</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Participants</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($program->sessions as $session)
                            <tr>
                                <td class="px-4 py-3 text-gray-800">{{ $session->start_date->format('d M Y') }} &ndash; {{ $session->end_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $session->location?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $session->participants()->count() }}{{ $session->max_participants ? ' / '.$session->max_participants : '' }}</td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $sessionStatusStyles[$session->status] }}">{{ ucfirst($session->status) }}</span></td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('training.sessions.show', $session) }}" class="text-slate-600 hover:underline">Manage</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No sessions scheduled yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <a href="{{ route('training.programs.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Back to Training Programs</a>
    </div>
</x-app-layout>
