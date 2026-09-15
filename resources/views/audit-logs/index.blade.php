@php
$actionStyles = ['created' => 'bg-green-100 text-green-800', 'updated' => 'bg-blue-100 text-blue-800', 'deleted' => 'bg-red-100 text-red-800'];
@endphp

<x-app-layout>
    <x-slot name="header">Audit Log</x-slot>

    <div class="space-y-4">
        <p class="text-sm text-gray-500">A read-only history of who created, changed, or removed records across the system.</p>

        <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3">
            <select name="auditable_type" class="rounded-md border-gray-300 text-sm">
                <option value="">All Record Types</option>
                @foreach ($auditableTypes as $type)
                    <option value="{{ $type }}" @selected(($filters['auditable_type'] ?? null) === $type)>{{ class_basename($type) }}</option>
                @endforeach
            </select>
            <select name="action" class="rounded-md border-gray-300 text-sm">
                <option value="">All Actions</option>
                <option value="created" @selected(($filters['action'] ?? null) === 'created')>Created</option>
                <option value="updated" @selected(($filters['action'] ?? null) === 'updated')>Updated</option>
                <option value="deleted" @selected(($filters['action'] ?? null) === 'deleted')>Deleted</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('audit-logs.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">When</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">User</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Action</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Record</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-gray-600">{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-800">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $actionStyles[$log->action] }}">{{ ucfirst($log->action) }}</span></td>
                            <td class="px-4 py-3 text-gray-800">{{ $log->auditableLabel() }} #{{ $log->auditable_id }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($log->changes)
                                    <details>
                                        <summary class="cursor-pointer text-slate-600">{{ count($log->changes) }} field(s)</summary>
                                        <ul class="mt-1 space-y-0.5 text-xs">
                                            @foreach ($log->changes as $field => $value)
                                                <li><span class="font-medium">{{ $field }}:</span> {{ is_scalar($value) ? $value : json_encode($value) }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No audit activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
</x-app-layout>
