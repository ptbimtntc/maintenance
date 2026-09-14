@php
$sessionStatusStyles = ['scheduled' => 'bg-blue-100 text-blue-800', 'ongoing' => 'bg-amber-100 text-amber-800', 'completed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-gray-100 text-gray-500'];
@endphp

<x-app-layout>
    <x-slot name="header">Training Calendar</x-slot>

    <div class="space-y-6">
        <form method="GET" class="flex gap-3 rounded-lg border border-gray-200 bg-white p-4">
            <select name="status" class="rounded-md border-gray-300 text-sm">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
            <a href="{{ route('training.calendar') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
        </form>

        @forelse ($sessionsByMonth as $month => $sessions)
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $month }}</h2>
                <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Dates</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Program</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Location</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($sessions as $session)
                                <tr>
                                    <td class="px-4 py-3 text-gray-800">{{ $session->start_date->format('d M') }} &ndash; {{ $session->end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $session->trainingProgram->title }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $session->location?->name ?? '—' }}</td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $sessionStatusStyles[$session->status] }}">{{ ucfirst($session->status) }}</span></td>
                                    <td class="px-4 py-3 text-right"><a href="{{ route('training.sessions.show', $session) }}" class="text-slate-600 hover:underline">Manage</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                No training sessions scheduled yet.
            </div>
        @endforelse
    </div>
</x-app-layout>
