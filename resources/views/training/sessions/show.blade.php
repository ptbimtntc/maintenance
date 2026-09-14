@php
$attendanceStyles = ['invited' => 'bg-gray-100 text-gray-600', 'confirmed' => 'bg-blue-100 text-blue-800', 'attended' => 'bg-green-100 text-green-800', 'absent' => 'bg-red-100 text-red-800'];
@endphp

<x-app-layout>
    <x-slot name="header">Session — {{ $trainingSession->trainingProgram->title }}</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $trainingSession->session_title ?? $trainingSession->trainingProgram->title }}</h2>
                    <p class="text-sm text-gray-500">{{ $trainingSession->start_date->format('d M Y') }} &ndash; {{ $trainingSession->end_date->format('d M Y') }} &middot; {{ $trainingSession->location?->name ?? 'No location set' }}</p>
                </div>
                @can(\App\Enums\PermissionName::ManageTraining->value)
                    <a href="{{ route('training.sessions.edit', $trainingSession) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                @endcan
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                Participants ({{ $trainingSession->participants->count() }}{{ $trainingSession->max_participants ? ' / '.$trainingSession->max_participants : '' }})
            </h3>

            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Attendance</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($trainingSession->participants as $participant)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $participant->employee->full_name }}</td>
                                <td class="px-4 py-3">
                                    @can(\App\Enums\PermissionName::ManageTraining->value)
                                        <form method="POST" action="{{ route('training.sessions.participants.update', [$trainingSession, $participant]) }}" class="inline-flex items-center gap-2">
                                            @csrf
                                            @method('PUT')
                                            <select name="attendance_status" onchange="this.form.submit()" class="rounded-md border-gray-300 text-xs">
                                                @foreach ($attendanceStatuses as $status)
                                                    <option value="{{ $status }}" @selected($participant->attendance_status === $status)>{{ ucfirst($status) }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $attendanceStyles[$participant->attendance_status] }}">{{ ucfirst($participant->attendance_status) }}</span>
                                    @endcan
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can(\App\Enums\PermissionName::ManageTraining->value)
                                        <form method="POST" action="{{ route('training.sessions.participants.destroy', [$trainingSession, $participant]) }}" onsubmit="return confirm('Remove this participant?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Remove</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No participants assigned yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @can(\App\Enums\PermissionName::ManageTraining->value)
            <div class="max-w-md rounded-lg border border-gray-200 bg-white p-6">
                <h3 class="text-sm font-semibold text-gray-900">Add Participant</h3>
                <form method="POST" action="{{ route('training.sessions.participants.store', $trainingSession) }}" class="mt-4 flex gap-2">
                    @csrf
                    <select name="employee_id" class="block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($availableEmployees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }} ({{ $employee->employee_number }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="shrink-0 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Add</button>
                </form>
            </div>
        @endcan

        <a href="{{ route('training.programs.show', $trainingSession->trainingProgram) }}" class="text-sm text-slate-600 hover:underline">&larr; Back to {{ $trainingSession->trainingProgram->title }}</a>
    </div>
</x-app-layout>
