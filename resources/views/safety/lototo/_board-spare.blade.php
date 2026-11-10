@php
    $boardColumns = (int) ($boardColumns ?? $board->layout['board_columns'] ?? $board->layout['technician_columns'] ?? 20);
    $spareColumns = (int) ($spareColumns ?? $boardColumns);
    $spareRows = (int) ($spareRows ?? max(1, (int) ceil(($spareSlots->max('position') ?? 0) / $spareColumns)));
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
        <div class="lototo-grid" style="--lototo-section-columns: {{ $spareColumns }};">
            @foreach ($spareSlots as $slot)
                @include('safety.lototo._slot-card', ['slot' => $slot, 'band' => 'spare'])
            @endforeach
        </div>
    </div>
</div>
