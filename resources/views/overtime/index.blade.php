@php
$statusStyles = [
    'locked' => 'bg-neutral-100 text-neutral-600',
    'edit_requested' => 'bg-warning-100 text-warning-800',
    'edit_approved' => 'bg-success-100 text-success-800',
];
@endphp

<x-app-layout>
    <x-slot name="header">Overtime</x-slot>

    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <p class="text-sm text-neutral-500">
                {{ $manage ? 'Overtime across all teams.' : 'Overtime for your team.' }}
            </p>
            <a href="{{ route('overtime.create') }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                Log Overtime
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-dashboard-stat label="Total Overtime Hours" :value="$totalHours" color="blue" />
            <x-dashboard-stat label="Total Entries" :value="$totalEntries" color="slate" />
            <x-dashboard-stat label="Pending Edit Requests" :value="$pendingEditRequests" color="amber" />
            @if ($manage)
                <x-dashboard-stat label="Not Yet Submitted to HR" :value="$notSubmittedCount" color="red" />
            @else
                <x-dashboard-stat label="OT_PAID Hours" :value="round($byCompensation['OT_PAID'] ?? 0, 2)" color="green" />
            @endif
        </div>

        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
            <h3 class="mb-3 text-sm font-semibold text-neutral-900">Overtime Trend (last 6 months)</h3>
            <div class="relative h-56 w-full">
                <canvas
                    data-chart
                    data-chart-type="bar-vertical"
                    data-chart-labels="{{ json_encode($trendLabels) }}"
                    data-chart-values="{{ json_encode($trendValues) }}"
                    data-chart-colors="{{ json_encode(array_fill(0, count($trendLabels), '#01ADEF')) }}"
                ></canvas>
            </div>
        </div>

        @if ($manage)
            <x-filter-panel :active-count="collect($filters)->filter(fn ($v) => filled($v))->count()">
                <form method="GET" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-3">
                    <select name="supervisor_id" class="rounded-md border-neutral-300 text-sm py-1.5">
                        <option value="">All Supervisors</option>
                        @foreach ($supervisors as $supervisor)
                            <option value="{{ $supervisor->id }}" @selected(($filters['supervisor_id'] ?? null) == $supervisor->id)>{{ $supervisor->full_name }}</option>
                        @endforeach
                    </select>
                    <select name="submission" class="rounded-md border-neutral-300 text-sm py-1.5">
                        <option value="">All</option>
                        <option value="pending" @selected(($filters['submission'] ?? null) === 'pending')>Not Submitted to HR</option>
                        <option value="submitted" @selected(($filters['submission'] ?? null) === 'submitted')>Submitted to HR</option>
                    </select>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Filter</button>
                        <a href="{{ route('overtime.index') }}" class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 transition shadow-sm hover:border-accent-400 hover:bg-accent-50 hover:text-accent-700">Reset</a>
                    </div>
                </form>
            </x-filter-panel>
        @endif

        <form id="overtime-bulk-form" x-data="{ checkedCount: 0 }">
            @if ($manage)
                <div class="mb-3 flex items-center gap-3">
                    <button type="submit" formaction="{{ route('overtime.export') }}" formmethod="GET" formtarget="_blank"
                            class="rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                        Export Selected (.xlsx)
                    </button>
                    <button type="submit" formaction="{{ route('overtime.mark-submitted') }}" formmethod="POST"
                            onclick="return confirm('Mark the selected entries as submitted to HR?')"
                            class="rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                        Mark Selected as Submitted to HR
                    </button>
                    <span class="text-xs text-neutral-400" x-show="checkedCount > 0" x-cloak x-text="checkedCount + ' selected'"></span>
                    <span class="text-xs text-neutral-400">No selection exports/marks every not-yet-submitted entry.</span>
                </div>
            @endif
            @csrf

            <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="border-b-2 border-brand-500 bg-brand-50">
                        <tr>
                            @if ($manage)
                                <th class="sticky top-0 z-10 bg-brand-50 w-10 px-3 py-2">
                                    <input type="checkbox" class="rounded border-neutral-300"
                                           @change="
                                               const boxes = $el.closest('table').querySelectorAll('tbody input[type=checkbox]');
                                               boxes.forEach(box => box.checked = $el.checked);
                                               checkedCount = $el.checked ? boxes.length : 0;
                                           ">
                                </th>
                                <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Supervisor</th>
                            @endif
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employee</th>
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">From</th>
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">To</th>
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Hours</th>
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Compensation</th>
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                            @if ($manage)
                                <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Submitted to HR</th>
                            @endif
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($entries as $entry)
                            <tr>
                                @if ($manage)
                                    <td class="px-3 py-2">
                                        <input type="checkbox" name="overtime_entry_ids[]" value="{{ $entry->id }}" class="rounded border-neutral-300"
                                               @change="checkedCount += $el.checked ? 1 : -1">
                                    </td>
                                    <td class="px-3 py-2 text-neutral-700">{{ $entry->employee->supervisor?->full_name ?? $entry->createdBy->name }}</td>
                                @endif
                                <td class="px-3 py-2 font-medium text-neutral-900">{{ $entry->employee->full_name }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ $entry->start_at->format('d M Y H:i') }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ $entry->end_at->format('d M Y H:i') }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ $entry->duration_hours }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ \App\Models\OvertimeEntry::compensationLabel($entry->compensation_type) }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$entry->status] }}">{{ \App\Models\OvertimeEntry::statusLabel($entry->status) }}</span>
                                    @if ($entry->status === 'edit_requested' && $entry->edit_request_reason)
                                        <p class="mt-1 max-w-xs text-xs text-neutral-400">"{{ $entry->edit_request_reason }}"</p>
                                    @endif
                                </td>
                                @if ($manage)
                                    <td class="px-3 py-2 text-neutral-600">{{ $entry->submitted_to_hr_at?->format('d M Y') ?? '—' }}</td>
                                @endif
                                <td class="px-3 py-2 text-right space-x-2">
                                    @if ($manage)
                                        @if ($entry->status === 'edit_requested')
                                            <button type="submit" form="approve-{{ $entry->id }}" class="text-success-700 hover:underline">Approve</button>
                                            <button type="submit" form="reject-{{ $entry->id }}" class="text-danger-600 hover:underline">Decline</button>
                                        @endif
                                        {{-- HR/Admin can correct any entry directly, locked or not - no approval round-trip needed for their own edits. --}}
                                        <a href="{{ route('overtime.edit', $entry) }}" class="text-brand-700 hover:underline">Edit</a>
                                    @else
                                        @if ($entry->status === 'locked')
                                            <button type="button" class="text-neutral-600 hover:underline" onclick="document.getElementById('request-edit-{{ $entry->id }}').classList.toggle('hidden')">Request Edit</button>
                                        @elseif ($entry->status === 'edit_approved')
                                            <a href="{{ route('overtime.edit', $entry) }}" class="text-brand-700 hover:underline">Edit</a>
                                        @else
                                            <span class="text-xs text-neutral-400">Awaiting approval</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            @if (! $manage && $entry->status === 'locked')
                                <tr id="request-edit-{{ $entry->id }}" class="hidden">
                                    <td colspan="7" class="bg-neutral-50 px-3 py-3">
                                        <form method="POST" action="{{ route('overtime.request-edit', $entry) }}" class="flex items-start gap-2">
                                            @csrf
                                            <input type="text" name="edit_request_reason" placeholder="Why does this entry need to change?" class="block w-full rounded-md border-neutral-300 text-sm" required>
                                            <button type="submit" class="shrink-0 rounded-md bg-brand-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-brand-700">Submit Request</button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-neutral-500">No overtime entries yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        @if ($manage)
            @foreach ($entries->where('status', 'edit_requested') as $entry)
                <form id="approve-{{ $entry->id }}" method="POST" action="{{ route('overtime.approve-edit', $entry) }}" class="hidden">@csrf</form>
                <form id="reject-{{ $entry->id }}" method="POST" action="{{ route('overtime.reject-edit', $entry) }}" class="hidden">@csrf</form>
            @endforeach
        @endif
    </div>
</x-app-layout>
