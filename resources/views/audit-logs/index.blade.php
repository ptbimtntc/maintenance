@php
$actionStyles = ['created' => 'bg-green-100 text-green-800', 'updated' => 'bg-accent-100 text-accent-800', 'deleted' => 'bg-red-100 text-red-800'];
@endphp

<x-app-layout>
    <x-slot name="header">Audit Log</x-slot>

    <div class="space-y-4">
        <p class="text-sm text-neutral-500">A read-only history of who created, changed, or removed records across the system.</p>

        @php
            $activeFieldClass = 'border-brand-400 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200';
            $defaultFieldClass = 'border-neutral-300';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-neutral-200 bg-white p-4 sm:grid-cols-3">
                <select name="auditable_type" class="rounded-md text-sm {{ filled($filters['auditable_type'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Record Types</option>
                    @foreach ($auditableTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['auditable_type'] ?? null) === $type)>{{ class_basename($type) }}</option>
                    @endforeach
                </select>
                <select name="action" class="rounded-md text-sm {{ filled($filters['action'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Actions</option>
                    <option value="created" @selected(($filters['action'] ?? null) === 'created')>Created</option>
                    <option value="updated" @selected(($filters['action'] ?? null) === 'updated')>Updated</option>
                    <option value="deleted" @selected(($filters['action'] ?? null) === 'deleted')>Deleted</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Filter</button>
                    <a href="{{ route('audit-logs.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">When</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">User</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Action</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Record</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-neutral-600">{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-neutral-800">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $actionStyles[$log->action] }}">{{ ucfirst($log->action) }}</span></td>
                            <td class="px-4 py-3 text-neutral-800">{{ $log->auditableLabel() }} #{{ $log->auditable_id }}</td>
                            <td class="px-4 py-3 text-neutral-600">
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
