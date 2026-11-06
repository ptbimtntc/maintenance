@php
    $assignedEmployee = $slot->employee;
    $activeActivity = $assignedEmployee ? $activeActivities->get($assignedEmployee->id) : null;
    // A slot without an enabled assignment that has a LOTOTO number stays plain; a lock that is IN is always shown.
    $employee = ($slot->is_enabled && filled($assignedEmployee?->lototo_number)) || $activeActivity ? $assignedEmployee : null;
@endphp

@if (! $employee)
    <div @class(['lototo-card lototo-empty-slot', ($cardClass ?? null) => filled($cardClass ?? null)]) aria-label="{{ $groupName }} posisi {{ $slot->position }} kosong">
        <span class="lototo-position-index">{{ sprintf('%02d', $slot->position) }}</span>
    </div>
@else
    @php
        $pendingFollowUp = $pendingFollowUps->get($employee->id)?->first();
    @endphp

    <a
        href="{{ route('safety.lototo.show', ['employee' => $employee, 'board' => $board->id]) }}"
        aria-label="{{ $employee->full_name }} LOTOTO {{ $employee->lototo_number }}{{ $activeActivity ? ', status IN' : ', status OUT' }}{{ $pendingFollowUp ? ', follow-up required' : '' }}"
        @class([
            'lototo-card relative border-r border-b border-neutral-400 text-center transition hover:z-10 hover:shadow-md',
            'lototo-active-card' => $activeActivity,
            'lototo-active-card--supervisor' => $activeActivity && $groupName === 'SUPERVISOR',
            'lototo-lock-card' => ! $activeActivity,
            'lototo-lock-card--supervisor' => ! $activeActivity && $groupName === 'SUPERVISOR',
            ($cardClass ?? null) => filled($cardClass ?? null),
        ])
    >
        <span class="lototo-position-index">{{ sprintf('%02d', $slot->position) }}</span>

        @if ($pendingFollowUp)
            <span
                class="lototo-follow-up-badge absolute right-1 top-1 z-10 flex h-4 w-4 items-center justify-center rounded-full bg-amber-400 text-neutral-900 shadow"
                title="LOTOTO follow-up required"
                aria-label="LOTOTO follow-up required"
                role="img"
            >
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12 2.25c.55 0 1.06.294 1.334.771l9.04 15.75c.562.98-.146 2.204-1.272 2.204H2.898c-1.126 0-1.834-1.224-1.272-2.204l9.04-15.75A1.54 1.54 0 0 1 12 2.25Zm0 5.25a.75.75 0 0 0-.75.75v4.5a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75Zm0 9a1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25Z" clip-rule="evenodd" />
                </svg>
            </span>
        @endif

        @include('safety.lototo._lock', ['employee' => $employee, 'in' => (bool) $activeActivity])
    </a>
@endif
