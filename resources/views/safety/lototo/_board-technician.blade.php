@php
    $technicianColumns = (int) ($technicianColumns ?? $boardColumns ?? $board->layout['board_columns'] ?? $board->layout['technician_columns'] ?? 20);
    $mainRows = (int) ($mainRows ?? $technicianRows ?? 1);
@endphp
<section class="lototo-main-group lototo-technician-group" style="grid-row: 1 / span {{ $mainRows }};">
    @if ($board->component_settings['technician'] ?? true)
    <div class="lototo-vertical-label lototo-vertical-label--technician">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path stroke-linecap="round" d="M8 10V7a4 4 0 1 1 8 0v3"/></svg>
        <span>TECHNICIAN</span>
    </div>
    <header class="lototo-section-heading lototo-section-heading--technician">
        <span class="lototo-heading-name">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path stroke-linecap="round" d="M8 10V7a4 4 0 1 1 8 0v3"/></svg>
            TECHNICIAN
        </span>
    </header>
    <div class="lototo-group-content min-w-0 flex-1">
        <div class="lototo-grid lototo-technician-grid" style="--lototo-grid-rows: {{ $mainRows }}; --lototo-section-columns: {{ $technicianColumns }};">
            {{-- Drawn by what each slot holds: a Technician slot as before, a slot changed to another category in
                 Edit Mode as that category (lototo-card--as-…), in this band. --}}
            @foreach ($technicianSlots as $slot)
                @include('safety.lototo._slot-card', ['slot' => $slot, 'band' => 'technician'])
            @endforeach
        </div>
    </div>
    @endif
</section>
