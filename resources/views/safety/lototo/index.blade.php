<x-app-layout>
    <x-slot name="header">LOTOTO</x-slot>

    <div class="lototo-page">
        <nav class="lototo-board-actions" aria-label="LOTOTO board controls">
            @if ($boards->isNotEmpty())
                <label class="lototo-board-select-wrap">
                    <span class="sr-only">Pilih board LOTOTO</span>
                    <select class="lototo-board-select" aria-label="Pilih board LOTOTO" onchange="if (this.value) window.location.href = this.value">
                        @foreach ($boards as $optionBoard)
                            @php
                                $businessUnit = $optionBoard->businessUnit;
                                $sameUnitCount = $boards->where('business_unit_id', $optionBoard->business_unit_id)->count();
                                $boardLabel = $businessUnit->name.($businessUnit->code ? ' · '.$businessUnit->code : '');
                                if ($sameUnitCount > 1) $boardLabel .= ' · '.$optionBoard->division_name;
                            @endphp
                            <option value="{{ route('safety.lototo.boards.show', $optionBoard) }}" @selected($optionBoard->is($board))>{{ $boardLabel }}</option>
                        @endforeach
                    </select>
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                </label>
            @else
                <span class="lototo-board-select-empty">Tidak ada board aktif yang dapat diakses</span>
            @endif
            <span class="lototo-board-action-spacer"></span>
            @can('safety.manage')
                <a href="{{ route('safety.lototo.boards.edit', $board) }}" class="lototo-board-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    Settings
                </a>
                <a href="{{ route('safety.lototo.settings', ['board' => $board->id]) }}" class="lototo-board-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1"/><rect x="14" y="3.5" width="6.5" height="6.5" rx="1"/><rect x="3.5" y="14" width="6.5" height="6.5" rx="1"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 14h2.5v2.5H14zM18 18h2.5v2.5H18zM18 14h2.5M14 18v2.5h1.5M6 6.2v1.1M16.7 6.2v1.1M6 16.7v1.1"/></svg>
                    QR Code
                </a>
            @endcan
        </nav>
        @php
            $boardColumns = (int) (
                $board->layout['board_columns']
                ?? $board->layout['technician_columns']
                ?? 20
            );
        @endphp
        <section
            class="lototo-board border-4 border-black p-1 shadow-lg"
            style="--lototo-board-columns: {{ $boardColumns }}; background: repeating-linear-gradient(135deg, #171717 0, #171717 7px, #facc15 7px, #facc15 14px);"
        >
        <div
                class="lototo-board-content border-2 border-neutral-700 bg-neutral-100"
                style="--lototo-board-columns: {{ $boardColumns }};"
            >
                @include('safety.lototo._board-header')

                @if ($lototoNumberConflicts->isNotEmpty())
                    <div role="alert" class="border-b border-amber-300 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-900">
                        Nomor LOTOTO duplikat terdeteksi: {{ $lototoNumberConflicts->implode(', ') }}. Periksa data karyawan agar penempatan slot tidak ambigu.
                    </div>
                @endif

                @php
                    $technicianSlots = $boardSlots->get('technician', collect());
                    $technicianSlotCount = $technicianSlots->count();
                    $supervisorSlots = $boardSlots->get('supervisor', collect());
                    $spareSlots = $boardSlots->get('spare', collect());
                    $toolSlots = $boardSlots->get('tool', collect());
                    $technicianSlotsByPosition = $technicianSlots->keyBy('position');
                    $techniciansByLototoNumber = $rows->get('TECHNICIAN', collect())
                        ->keyBy(fn ($employee) => (int) $employee->lototo_number);
                    $highestTechnicianNumber = (int) ($rows->get('TECHNICIAN', collect())->max(fn ($employee) => (int) $employee->lototo_number) ?? 0);
                    $technicianPositionCount = $board->code === 'mtn_tr'
                        ? $highestTechnicianNumber
                        : max((int) ($technicianSlots->max('position') ?? 0), $highestTechnicianNumber);
                    $technicianPositionCount = max(1, $technicianPositionCount);
                    $technicianSlots = collect(range(1, max(1, $technicianPositionCount)))->map(function ($position) use ($technicianSlotsByPosition, $techniciansByLototoNumber, $board) {
                        $slot = $technicianSlotsByPosition->get($position)
                            ?? new \App\Models\LototoBoardSlot([
                                'board_id' => $board->id,
                                'category' => 'technician',
                                'position' => $position,
                                'is_enabled' => true,
                            ]);
                        if ($board->code === 'mtn_tr') {
                            $slot->setRelation('employee', $techniciansByLototoNumber->get($position));
                        }

                        return $slot;
                    })->values();
                    $supervisorPositionCount = (int) ($supervisorSlots->max('position') ?? 0);
                    $supervisorSlotsByPosition = $supervisorSlots->keyBy('position');
                    $supervisorSlots = collect(range(1, max(1, $supervisorPositionCount)))->map(fn ($position) => $supervisorSlotsByPosition->get($position)
                        ?? new \App\Models\LototoBoardSlot(['board_id' => $board->id, 'category' => 'supervisor', 'position' => $position, 'is_enabled' => true]))->values();
                    $sparePositionCount = (int) ($spareSlots->max('position') ?? 0);
                    $spareSlotsByPosition = $spareSlots->keyBy('position');
                    $spareSlots = collect($sparePositionCount > 0 ? range(1, $sparePositionCount) : [])->map(fn ($position) => $spareSlotsByPosition->get($position)
                        ?? new \App\Models\LototoBoardSlot(['board_id' => $board->id, 'category' => 'spare', 'position' => $position, 'is_enabled' => true]))->values();
                    $toolSlotsByPosition = $toolSlots->keyBy('position');
                    $toolPositionCount = (int) ($toolSlots->max('position') ?? 0);
                    $toolSlots = collect($toolPositionCount > 0 ? range(1, $toolPositionCount) : [])
                        ->map(fn ($position) => $toolSlotsByPosition->get($position)
                            ?? new \App\Models\LototoBoardSlot([
                                'board_id' => $board->id,
                                'category' => 'tool',
                                'position' => $position,
                                'is_enabled' => true,
                            ]))
                        ->values();
                    $technicianColumns = $boardColumns;
                    $hasTechnicians = ($board->component_settings['technician'] ?? true) && $technicianPositionCount > 0;
                    $technicianRows = $hasTechnicians ? (int) ceil($technicianPositionCount / $technicianColumns) : 0;
                    $technicianLastRowCount = $hasTechnicians ? ($technicianPositionCount - (($technicianRows - 1) * $technicianColumns)) : 0;
                    $hasSupervisors = (bool) ($board->component_settings['supervisor'] ?? true);
                    $supervisorSlotCount = max(1, $supervisorPositionCount);
                    // Supervisor is right-aligned beside the last, partly filled Technician row unless that
                    // needs more extra rows than starting below Technician; either way it wraps in equal rows.
                    // Its slots are as wide as a board column and its label is drawn left of them, so one free
                    // column is kept for the label.
                    $technicianFreeColumns = $hasTechnicians ? $boardColumns - $technicianLastRowCount - 1 : 0;
                    $supervisorRowsBelow = (int) ceil($supervisorSlotCount / max(1, $boardColumns - 1));
                    $supervisorRowsBeside = $technicianFreeColumns > 0 ? (int) ceil($supervisorSlotCount / $technicianFreeColumns) : 0;
                    $supervisorBeside = $technicianFreeColumns > 0 && $supervisorRowsBeside - 1 <= $supervisorRowsBelow;
                    $supervisorRows = $supervisorBeside ? $supervisorRowsBeside : $supervisorRowsBelow;
                    $supervisorCols = (int) ceil($supervisorSlotCount / $supervisorRows);
                    $supervisorStartRow = $supervisorBeside ? $technicianRows : $technicianRows + 1;
                    $supervisorStartColumn = $boardColumns - $supervisorCols + 1;
                    // Grid track 1 holds the Technician label, so board column N is track N + 1.
                    $supervisorStartTrack = $supervisorStartColumn + 1;
                    $mainRows = $hasSupervisors
                        ? max($technicianRows, $supervisorStartRow + $supervisorRows - 1)
                        : max($technicianRows, 1);

                    $spareRows = max(1, (int) ceil(($spareSlots->max('position') ?? 0) / $boardColumns));
                    $toolColumns = $boardColumns;
                    $toolPlacements = [];
                    $toolGridRow = 1;
                    $toolGridColumn = 1;
                    foreach ($toolSlots as $toolSlot) {
                        $toolSpan = max(1, min(3, (int) ($toolSlot->tool_column_span ?? 1)));
                        if ($toolGridColumn + $toolSpan - 1 > $toolColumns) {
                            $toolGridRow++;
                            $toolGridColumn = 1;
                        }
                        $toolPlacements[$toolSlot->position] = [
                            'column' => $toolGridColumn,
                            'span' => $toolSpan,
                            'row' => $toolGridRow,
                        ];
                        $toolGridColumn += $toolSpan;
                        if ($toolGridColumn > $toolColumns) {
                            $toolGridRow++;
                            $toolGridColumn = 1;
                        }
                    }
                    $toolRows = max(1, $toolGridRow - ($toolGridColumn === 1 ? 1 : 0));
                @endphp

                <div
                    class="lototo-main-area border-b-2 border-neutral-700"
                    style="
                        --lototo-board-columns: {{ $boardColumns }};
                        --lototo-row-count: {{ $mainRows }};
                        --lototo-grid-rows: {{ $mainRows }};
                    "
                >
                @if (($board->component_settings['technician'] ?? true) || ($board->component_settings['supervisor'] ?? true))
                @include('safety.lototo._board-technician', [
                        'technicianSlots' => $technicianSlots,
                        'technicianSlotCount' => $technicianSlotCount,
                        'technicianColumns' => $technicianColumns,
                        'mainRows' => $mainRows,
                        'technicianRows' => $technicianRows,
                        'activeActivities' => $activeActivities,
                        'pendingFollowUps' => $pendingFollowUps,
                    ])
                @endif

                    @if ($board->component_settings['supervisor'] ?? true)
                    @include('safety.lototo._board-supervisor', [
                        'supervisorSlots' => $supervisorSlots,
                        'supervisorStartColumn' => $supervisorStartColumn,
                        'supervisorStartTrack' => $supervisorStartTrack,
                        'supervisorStartRow' => $supervisorStartRow,
                        'mainRows' => $mainRows,
                        'supervisorRows' => $supervisorRows,
                        'supervisorCols' => $supervisorCols,
                        'boardColumns' => $boardColumns,
                        'activeActivities' => $activeActivities,
                        'pendingFollowUps' => $pendingFollowUps,
                    ])
                    @endif

                </div>

                @foreach (($board->layout['section_order'] ?? ['spare', 'other']) as $sectionIndex => $section)
                    @if ($section === 'other' && ($board->component_settings['other'] ?? true))
                        @include('safety.lototo._board-other-tools', ['toolSlots' => $toolSlots, 'toolColumns' => $toolColumns, 'toolRows' => $toolRows, 'boardColumns' => $boardColumns, 'sectionOrder' => $sectionIndex + 2])
                    @elseif ($section === 'spare' && ($board->component_settings['spare'] ?? true))
                        @include('safety.lototo._board-spare', ['spareSlots' => $spareSlots, 'spareRows' => $spareRows, 'boardColumns' => $boardColumns, 'sectionOrder' => $sectionIndex + 2])
                    @endif
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
