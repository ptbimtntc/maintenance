@php
    $boardColumns = (int) ($boardColumns ?? $board->layout['board_columns'] ?? $board->layout['technician_columns'] ?? 20);
    $supervisorCols = (int) ($supervisorCols ?? $boardColumns);
    $supervisorStartTrack = (int) ($supervisorStartTrack ?? (($supervisorStartColumn ?? 1) + 1));
    $supervisorStartRow = (int) ($supervisorStartRow ?? 1);
    $supervisorRows = (int) ($supervisorRows ?? 1);
@endphp
<section
    class="lototo-main-group lototo-supervisor-group"
    style="grid-column: {{ $supervisorStartTrack }} / -1; grid-row: {{ $supervisorStartRow }} / span {{ $supervisorRows }};"
>
    <div class="lototo-vertical-label lototo-vertical-label--supervisor"><span>SUPERVISOR</span></div>
    <div class="lototo-group-content min-w-0 flex-1">
        <div class="lototo-grid lototo-supervisor-grid" style="--lototo-grid-rows: {{ $supervisorRows }}; --lototo-supervisor-columns: {{ $supervisorCols }};">
            {{-- Supervisor positions are numbered from the right: 01 is the rightmost slot of the first row. --}}
            @foreach ($supervisorSlots as $slot)
                <div style="grid-column: {{ $supervisorCols - (($slot->position - 1) % $supervisorCols) }}; grid-row: {{ intdiv($slot->position - 1, $supervisorCols) + 1 }};">
                    @include('safety.lototo._employee-slot', [
                        'slot' => $slot,
                        'groupName' => 'SUPERVISOR',
                        'activeActivities' => $activeActivities,
                        'pendingFollowUps' => $pendingFollowUps,
                    ])
                </div>
            @endforeach
        </div>
    </div>
</section>
