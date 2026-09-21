@php
$completionStyles = ['completed' => 'bg-green-100 text-green-800', 'incomplete' => 'bg-amber-100 text-amber-800', 'failed' => 'bg-red-100 text-red-800'];
@endphp

<x-app-layout>
    <x-slot name="header">Training Records</x-slot>

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <p class="text-sm text-neutral-500">{{ $records->total() }} training record(s) found.</p>
                <x-read-only-badge menu="training" />
            </div>

            @can(\App\Enums\PermissionName::ManageTrainingRecords->value)
                <div class="flex items-center gap-2">
                    <a href="{{ route('training.records.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}"
                       class="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Export XLSX
                    </a>

                    <form method="POST" action="{{ route('training.records.import') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-4.5L12 16.5m0 0 4.5-4.5M12 16.5V3" />
                            </svg>
                            Import XLSX
                            <input type="file" name="file" accept=".xlsx" class="hidden" onchange="this.form.requestSubmit()">
                        </label>
                    </form>
                </div>
            @endcan
        </div>

        @if (session('import_errors'))
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ session('import_errors') }}
            </div>
        @endif

        @php
            $activeFieldClass = 'border-brand-400 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200';
            $defaultFieldClass = 'border-neutral-300';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-neutral-200 bg-white p-4 sm:grid-cols-3">
                <input type="text" name="employee_search" value="{{ $filters['employee_search'] ?? '' }}" placeholder="Search employee..." class="rounded-md text-sm {{ filled($filters['employee_search'] ?? null) ? $activeFieldClass : $defaultFieldClass }}" />
                <select name="completion_status" class="rounded-md text-sm {{ filled($filters['completion_status'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Completion Statuses</option>
                    @foreach ($completionStatuses as $status)
                        <option value="{{ $status }}" @selected(($filters['completion_status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Filter</button>
                    <a href="{{ route('training.records.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employee</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Program</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Date</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Duration (hrs)</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Completion</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($records as $record)
                        <tr>
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $record->employee->full_name }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $record->trainingProgram?->title ?? '—' }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $record->training_date->format('d M Y') }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $record->duration_hours ?? '—' }}</td>
                            <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $completionStyles[$record->completion_status] }}">{{ ucfirst($record->completion_status) }}</span></td>
                            <td class="px-3 py-2 text-right"><a href="{{ route('employees.show', $record->employee) }}" class="text-neutral-600 hover:underline">View Employee</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-neutral-500">No training records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $records->links() }}
    </div>
</x-app-layout>
