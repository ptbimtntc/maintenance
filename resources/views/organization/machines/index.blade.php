@php
$backRoute = route('organization.landing');
$backLabel = 'Back to Master Data';
$originQuery = array_filter(['from' => $from]);
$hasFilters = filled($search) || filled($machineTypeFilter);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $config['label'] }}</x-slot>

    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ $backRoute }}" class="text-sm text-neutral-600 hover:underline">&larr; {{ $backLabel }}</a>
            <div class="flex flex-wrap items-center gap-2">
                <x-button variant="secondary" href="{{ route('organization.export', $type) }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Export XLSX
                </x-button>

                @if (auth()->user()->canEditMenu(\App\Enums\MenuKey::Organization))
                    <form method="POST" action="{{ route('organization.import', $type) }}" enctype="multipart/form-data">
                        @csrf
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-4.5L12 16.5m0 0 4.5-4.5M12 16.5V3" />
                            </svg>
                            Import XLSX
                            <input type="file" name="file" accept=".xlsx" class="hidden" onchange="this.form.requestSubmit()">
                        </label>
                    </form>

                    <div class="h-6 w-px bg-neutral-200"></div>
                @endif

                <x-button href="{{ route('organization.create', [$type, ...$originQuery]) }}">
                    Add {{ $config['singular'] }}
                </x-button>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        @if (session('import_errors'))
            <div class="rounded-md border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">{{ session('import_errors') }}</div>
        @endif

        <!-- Filter & Search Bar -->
        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('organization.index', $type) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if ($from)
                    <input type="hidden" name="from" value="{{ $from }}">
                @endif

                <div class="relative flex-1">
                    <input type="text"
                           name="q"
                           value="{{ $search }}"
                           placeholder="Search machine no., func. location, or type..."
                           class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm placeholder-neutral-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                </div>

                <div class="sm:w-64">
                    <select name="machine_type"
                            onchange="this.form.submit()"
                            class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <option value="">All Machine Types</option>
                        @foreach ($availableMachineTypes as $mType)
                            <option value="{{ $mType }}" @selected($machineTypeFilter === $mType)>{{ $mType }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <x-button type="submit">Filter</x-button>
                    @if ($hasFilters)
                        <a href="{{ route('organization.index', array_filter(['type' => $type, 'from' => $from])) }}"
                           class="text-sm text-neutral-600 hover:text-neutral-900 hover:underline">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Machine Number</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Func. Location</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Machine Type</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($records as $record)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $record->machine_no }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $record->func_location ?? '—' }}</td>
                            <td class="px-3 py-2 text-neutral-800">
                                <span class="inline-flex rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-800">
                                    {{ $record->machine_type }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('organization.edit', [$type, $record->id, ...$originQuery]) }}" class="font-medium text-accent-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('organization.destroy', [$type, $record->id, ...$originQuery]) }}" class="inline"
                                      data-confirm="Delete this {{ strtolower($config['singular']) }}? This cannot be undone.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ml-3 text-danger-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-neutral-500">
                                {{ $hasFilters ? 'No matching machines found.' : 'No '.strtolower($config['label']).' yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="mt-4">
                {{ $records->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
