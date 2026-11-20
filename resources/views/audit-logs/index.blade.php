@php
$actionStyles = ['created' => 'bg-success-100 text-success-800', 'updated' => 'bg-accent-100 text-accent-800', 'deleted' => 'bg-danger-100 text-danger-800'];
@endphp

<x-app-layout>
    <x-slot name="header">Audit Log</x-slot>

    <div class="space-y-4">
        <p class="text-sm text-neutral-500">A read-only history of who created, changed, or removed records across the system.</p>

        @php
            $activeFieldClass = 'border-brand-300 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200 shadow-sm';
            $defaultFieldClass = 'border-neutral-300 hover:border-accent-400';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-3">
                <x-multi-select-filter name="auditable_type" label="All Record Types"
                    :options="collect($auditableTypes)->mapWithKeys(fn ($t) => [$t => class_basename($t)])" :selected="$filters['auditable_type'] ?? []" />
                <x-multi-select-filter name="action" label="All Actions"
                    :options="['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']" :selected="$filters['action'] ?? []" />
                <div class="flex gap-2">
                    <x-button size="sm" type="submit">Filter</x-button>
                    <x-button variant="secondary" size="sm" href="{{ route('audit-logs.index') }}">Reset</x-button>
                </div>
            </form>
        </x-filter-panel>

        <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">When</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">User</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Action</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Record</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-3 py-2 text-neutral-600">{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td class="px-3 py-2 text-neutral-800">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $actionStyles[$log->action] }}">{{ ucfirst($log->action) }}</span></td>
                            <td class="px-3 py-2 text-neutral-800">{{ $log->auditableLabel() }} #{{ $log->auditable_id }}</td>
                            <td class="px-3 py-2 text-neutral-600">
                                @if ($log->changes)
                                    <details>
                                        <summary class="cursor-pointer text-neutral-600">{{ count($log->changes) }} field(s)</summary>
                                        <ul class="mt-1 space-y-0.5 text-xs">
                                            @foreach ($log->changes as $field => $value)
                                                <li><span class="font-medium">{{ $field }}:</span> {{ is_scalar($value) ? $value : json_encode($value) }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <span class="text-neutral-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-neutral-500">No audit activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
</x-app-layout>
