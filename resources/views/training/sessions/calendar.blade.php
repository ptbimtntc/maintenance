@php
$sessionStatusStyles = ['scheduled' => 'bg-accent-100 text-accent-800', 'ongoing' => 'bg-amber-100 text-amber-800', 'completed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-neutral-100 text-neutral-500'];
@endphp

<x-app-layout>
    <x-slot name="header">Training Calendar</x-slot>

    <div class="space-y-6">
        @php
            $activeFieldClass = 'border-brand-400 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200';
            $defaultFieldClass = 'border-neutral-300';
            $filterActiveCount = collect($filters)->except(['view', 'month'])->filter(fn ($value) => filled($value))->count();
        @endphp

        <div class="flex flex-wrap items-start justify-between gap-3">
            <x-filter-panel :active-count="$filterActiveCount">
                <form method="GET" class="flex flex-wrap gap-3 rounded-lg border border-neutral-200 bg-white p-4">
                    <input type="hidden" name="view" value="{{ $view }}">
                    @if ($view === 'grid')
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    @endif
                    <select name="status" class="rounded-md text-sm {{ filled($filters['status'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                        <option value="">All Statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Filter</button>
                    <a href="{{ route('training.calendar', ['view' => $view]) }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Reset</a>
                </form>
            </x-filter-panel>

            <div class="flex overflow-hidden rounded-md border border-neutral-300">
                <a href="{{ route('training.calendar', array_merge($filters, ['view' => 'list'])) }}"
                   class="px-4 py-2 text-sm font-medium {{ $view === 'list' ? 'bg-brand-600 text-white' : 'bg-white text-neutral-700 hover:bg-neutral-50' }}">List</a>
                <a href="{{ route('training.calendar', array_merge($filters, ['view' => 'grid'])) }}"
                   class="px-4 py-2 text-sm font-medium {{ $view === 'grid' ? 'bg-brand-600 text-white' : 'bg-white text-neutral-700 hover:bg-neutral-50' }}">Grid</a>
            </div>
        </div>

        @if ($view === 'grid')
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <div class="mb-4 flex items-center justify-between">
                    <a href="{{ route('training.calendar', array_merge($filters, ['view' => 'grid', 'month' => $month->copy()->subMonth()->format('Y-m')])) }}"
                       class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50">&larr; Prev</a>
                    <h2 class="text-lg font-semibold text-neutral-900">{{ $month->format('F Y') }}</h2>
                    <a href="{{ route('training.calendar', array_merge($filters, ['view' => 'grid', 'month' => $month->copy()->addMonth()->format('Y-m')])) }}"
                       class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Next &rarr;</a>
                </div>

                <div class="grid grid-cols-7 gap-px overflow-hidden rounded-md border border-neutral-200 bg-neutral-200 text-xs">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label)
                        <div class="bg-neutral-50 px-2 py-1.5 text-center font-medium uppercase tracking-wide text-neutral-500">{{ $label }}</div>
                    @endforeach

                    @foreach ($weeks as $week)
                        @foreach ($week as $day)
                            <div class="min-h-[6rem] bg-white p-1.5 align-top {{ $day['inMonth'] ? '' : 'bg-neutral-50 text-neutral-400' }}">
                                <div class="text-right text-xs {{ $day['date']->isToday() ? 'font-bold text-neutral-900' : 'text-neutral-500' }}">
                                    {{ $day['date']->day }}
                                </div>
                                <div class="mt-1 space-y-1">
                                    @foreach ($day['sessions'] as $session)
                                        <a href="{{ route('training.sessions.show', $session) }}"
                                           class="block truncate rounded px-1.5 py-0.5 {{ $sessionStatusStyles[$session->status] }}"
                                           title="{{ $session->trainingProgram->title }}">
                                            {{ $session->trainingProgram->title }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @else
        @forelse ($sessionsByMonth as $month => $sessions)
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ $month }}</h2>
                <div class="mt-3 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-neutral-500">Dates</th>
                                <th class="px-4 py-3 text-left font-medium text-neutral-500">Program</th>
                                <th class="px-4 py-3 text-left font-medium text-neutral-500">Location</th>
                                <th class="px-4 py-3 text-left font-medium text-neutral-500">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($sessions as $session)
                                <tr>
                                    <td class="px-4 py-3 text-neutral-800">{{ $session->start_date->format('d M') }} &ndash; {{ $session->end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 font-medium text-neutral-900">{{ $session->trainingProgram->title }}</td>
                                    <td class="px-4 py-3 text-neutral-600">{{ $session->location?->name ?? '—' }}</td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $sessionStatusStyles[$session->status] }}">{{ ucfirst($session->status) }}</span></td>
                                    <td class="px-4 py-3 text-right"><a href="{{ route('training.sessions.show', $session) }}" class="text-neutral-600 hover:underline">Manage</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center text-sm text-neutral-500">
                No training sessions scheduled yet.
            </div>
        @endforelse
        @endif
    </div>
</x-app-layout>
