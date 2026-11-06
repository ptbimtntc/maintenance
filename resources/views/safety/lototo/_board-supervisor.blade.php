@php
    $supervisorCols = (int) ($supervisorCols ?? $board->layout['supervisor_columns'] ?? $board->layout['board_columns'] ?? 20);
    $supervisorRows = (int) ($supervisorRows ?? 1);
@endphp
<section
    class="lototo-main-group lototo-supervisor-group"
    style="grid-column: 1 / -1; grid-row: 1 / span {{ $supervisorRows }};"
>
    <div class="lototo-vertical-label lototo-vertical-label--supervisor"><span>SUPERVISOR</span></div>
    <div class="lototo-group-content min-w-0 flex-1">
        <div class="lototo-grid lototo-supervisor-grid" style="--lototo-grid-rows: {{ $supervisorRows }}; --lototo-supervisor-columns: {{ $supervisorCols }};">
            {{-- Each slot sits in its stored cell; by number, 01 is the leftmost slot of the first row and rows wrap after the column count. --}}
            @foreach ($supervisorSlots as $slot)
                @include('safety.lototo._slot-card', ['slot' => $slot, 'band' => 'supervisor'])
            @endforeach
        </div>
    </div>
</section>
