@php
    $boardColumns = (int) ($boardColumns ?? $board->layout['board_columns'] ?? $board->layout['technician_columns'] ?? 20);
    $spareRows = (int) ($spareRows ?? max(1, (int) ceil(($spareSlots->max('position') ?? 0) / $boardColumns)));
@endphp
<div
    class="lototo-group lototo-spare-group flex min-w-0 border-b-2 border-neutral-700"
    style="--lototo-row-count: {{ $spareRows }}; --lototo-grid-rows: {{ $spareRows }}; order: {{ $sectionOrder }};"
>
    <div class="lototo-vertical-label lototo-vertical-label--spare">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 10V7.5a4.5 4.5 0 0 1 9 0V10"/><rect x="4" y="10" width="16" height="12" rx="2"/></svg>
        <span>SPARE</span>
    </div>
    <div class="lototo-group-content min-w-0 flex-1">
        <div class="lototo-grid">
            @foreach ($spareSlots as $slot)
                @php
                    $assignedSpareLock = $slot->spareLock;
                    $spareLock = $slot->is_enabled || $assignedSpareLock?->currentActivity
                        ? $assignedSpareLock
                        : null;
                    $gridColumn = (($slot->position - 1) % $boardColumns) + 1;
                    $gridRow = intdiv($slot->position - 1, $boardColumns) + 1;
                @endphp
                @if (! $spareLock)
                    <div
                        class="lototo-card lototo-empty-slot"
                        style="grid-column: {{ $gridColumn }}; grid-row: {{ $gridRow }};"
                        aria-label="Spare posisi {{ $slot->position }} kosong"
                    >
                        <span class="lototo-position-index">{{ sprintf('%02d', $slot->position) }}</span>
                    </div>
                @else
                    @php
                        $spareActivity = $spareLock->currentActivity;
                        $spareEmployee = $spareActivity?->employee;
                    @endphp
                <a
                    href="{{ route('safety.lototo.spare-locks.show', $spareLock) }}"
                    style="grid-column: {{ $gridColumn }}; grid-row: {{ $gridRow }};"
                    @class([
                        'lototo-card border-r border-b border-neutral-400 text-center transition hover:z-10 hover:shadow-md',
                        'lototo-spare-lock--inactive' => ! $spareLock->is_active,
                        'lototo-active-card lototo-active-card--spare' => $spareActivity !== null,
                        'lototo-lock-card lototo-lock-card--spare' => $spareActivity === null,
                    ])
                    aria-label="Gembok spare {{ $spareLock->code }}"
                >
                    <span class="lototo-position-index">{{ sprintf('%02d', $slot->position) }}</span>
                    @include('safety.lototo._lock', [
                        'employee' => $spareEmployee,
                        'in' => $spareActivity !== null,
                        'band' => 'SPARE',
                        'code' => $spareLock->code,
                    ])
                </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
