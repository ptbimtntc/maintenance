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
            @foreach ($technicianSlots as $slot)
                <div data-slot-category="technician" data-slot-position="{{ $slot->position }}" data-grid-row="{{ $slot->grid_row }}" data-grid-column="{{ $slot->grid_column }}" @if ($slot->id) data-slot-id="{{ $slot->id }}" @endif style="grid-column: {{ $slot->grid_column }}; grid-row: {{ $slot->grid_row }};">
                    @include('safety.lototo._employee-slot', [
                        'slot' => $slot,
                        'groupName' => 'TECHNICIAN',
                        'activeActivities' => $activeActivities,
                        'pendingFollowUps' => $pendingFollowUps,
                    ])
                </div>
            @endforeach
        </div>
    </div>
    @endif
</section>
