<x-app-layout>
    <x-slot name="header">LOTOTO</x-slot>

    @php
        // Each category has its own column count; the board is as wide as the widest visible one, so a slot
        // is always one board column wide and narrower categories simply leave the rest of their row free.
        $categoryColumns = \App\Http\Controllers\LototoBoardSettingsController::layoutColumns($board);
        $sectionOrder = \App\Http\Controllers\LototoBoardSettingsController::layoutSectionOrder($board->layout['section_order'] ?? null);
        $componentVisible = fn (string $component) => (bool) ($board->component_settings[$component] ?? true);
        $boardColumns = collect(['technician' => 'technician', 'supervisor' => 'supervisor', 'spare' => 'spare', 'tool' => 'other'])
            ->filter(fn ($component) => $componentVisible($component))
            ->keys()
            ->map(fn ($category) => $categoryColumns[$category])
            ->max() ?? $categoryColumns['technician'];
        // Edit Mode markup is only rendered for Safety managers; the layout endpoints check the same permission.
        $canEditBoard = (bool) auth()->user()?->can(\App\Enums\PermissionName::ManageSafety->value);
    @endphp
    <div
        @class(['lototo-page', 'lototo-page--wide' => $boardColumns > 20])
        @if ($canEditBoard)
            data-board-editor
            data-layout-url="{{ route('safety.lototo.boards.layout', $board) }}"
            data-save-url="{{ route('safety.lototo.boards.layout.update', $board) }}"
            data-tools-url="{{ route('safety.lototo.boards.tools.store', $board) }}"
            data-spare-locks-url="{{ route('safety.lototo.boards.spare-locks.store', $board) }}"
        @endif
    >
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
            @if ($canEditBoard)
                {{-- Edit Mode only: the board's one column count (board_columns, the same value Settings showed). --}}
                <div class="lototo-editor-columns" data-editor-columns hidden>
                    <label class="lototo-editor-columns__field">
                        <span>Kolom per baris:</span>
                        <select data-editor-column-count aria-label="Kolom per baris untuk seluruh board">
                            @foreach (range(\App\Http\Controllers\LototoBoardSettingsController::MIN_COLUMNS, \App\Http\Controllers\LototoBoardSettingsController::MAX_COLUMNS) as $count)
                                <option value="{{ $count }}">{{ $count }}</option>
                            @endforeach
                        </select>
                    </label>
                    <span class="lototo-editor-columns__note" data-editor-columns-note hidden></span>
                </div>
                <button type="button" class="lototo-board-action" data-board-edit-start>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                    Edit Board
                </button>
            @endif
            <span class="lototo-board-action-spacer"></span>
            @can('safety.manage')
                <a href="{{ route('safety.lototo.boards.index', ['board' => $board->id]) }}" class="lototo-board-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="4" width="17" height="16" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 9h17M9 9v11"/></svg>
                    Manage Board
                </a>
            @endcan
            @if (\App\Http\Controllers\LototoBoardSettingsController::canViewQrCodes(auth()->user()))
                <a href="{{ route('safety.lototo.qr-codes', ['board' => $board->id]) }}" class="lototo-board-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1"/><rect x="14" y="3.5" width="6.5" height="6.5" rx="1"/><rect x="3.5" y="14" width="6.5" height="6.5" rx="1"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 14h2.5v2.5H14zM18 18h2.5v2.5H18zM18 14h2.5M14 18v2.5h1.5M6 6.2v1.1M16.7 6.2v1.1M6 16.7v1.1"/></svg>
                    QR Code
                </a>
            @endif
        </nav>
        @if ($canEditBoard)
            @include('safety.lototo._board-editor')
        @endif
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
                    $technicianSlotCount = $boardSlots->get('technician', collect())->count();
                    $highestTechnicianNumber = (int) ($rows->get('TECHNICIAN', collect())->max(fn ($employee) => (int) $employee->lototo_number) ?? 0);
                    // Every slot is drawn in its stored cell (grid_row / grid_column), not where its number would put it,
                    // and shows the employee saved on it. Positions without a slot up to the highest number (for
                    // Technician also the highest LOTOTO number) are drawn empty where nothing else is.
                    $drawn = fn (string $category, int $through) => \App\Support\LototoSlotGrid::drawn($category, $boardSlots->get($category, collect()), $categoryColumns[$category], $board->id, $through);
                    ['slots' => $technicianSlots, 'rows' => $technicianRows] = $drawn('technician', max(1, $highestTechnicianNumber));
                    ['slots' => $supervisorSlots, 'rows' => $supervisorRows] = $drawn('supervisor', 1);
                    ['slots' => $spareSlots, 'rows' => $spareRows] = $drawn('spare', 0);
                    ['slots' => $toolSlots, 'rows' => $toolRows] = $drawn('tool', 0);
                    $technicianColumns = $categoryColumns['technician'];
                    $mainRows = max($technicianRows, 1);
                    // Supervisor is its own section, 01 at the left of the first row.
                    $supervisorCols = $categoryColumns['supervisor'];
                    $spareColumns = $categoryColumns['spare'];
                    $toolColumns = $categoryColumns['tool'];
                @endphp

                @if ($componentVisible('technician'))
                <div
                    class="lototo-main-area border-b-2 border-neutral-700"
                    style="
                        --lototo-board-columns: {{ $boardColumns }};
                        --lototo-row-count: {{ $mainRows }};
                        --lototo-grid-rows: {{ $mainRows }};
                    "
                >
                @include('safety.lototo._board-technician', [
                        'technicianSlots' => $technicianSlots,
                        'technicianSlotCount' => $technicianSlotCount,
                        'technicianColumns' => $technicianColumns,
                        'mainRows' => $mainRows,
                        'technicianRows' => $technicianRows,
                        'activeActivities' => $activeActivities,
                        'pendingFollowUps' => $pendingFollowUps,
                    ])
                </div>
                @endif

                @foreach ($sectionOrder as $sectionIndex => $section)
                    @if ($section === 'other' && $componentVisible('other'))
                        @include('safety.lototo._board-other-tools', ['toolSlots' => $toolSlots, 'toolColumns' => $toolColumns, 'toolRows' => $toolRows, 'boardColumns' => $boardColumns, 'sectionOrder' => $sectionIndex + 2])
                    @elseif ($section === 'spare' && $componentVisible('spare'))
                        @include('safety.lototo._board-spare', ['spareSlots' => $spareSlots, 'spareRows' => $spareRows, 'spareColumns' => $spareColumns, 'boardColumns' => $boardColumns, 'sectionOrder' => $sectionIndex + 2])
                    @elseif ($section === 'supervisor' && $componentVisible('supervisor'))
                        {{-- Built like the Technician area (label track plus board columns), so Supervisor slots and
                             locks keep the Technician slot size and styling. --}}
                        <div
                            class="lototo-main-area lototo-supervisor-area border-b-2 border-neutral-700"
                            style="--lototo-board-columns: {{ $boardColumns }}; --lototo-row-count: {{ $supervisorRows }}; --lototo-grid-rows: {{ $supervisorRows }}; order: {{ $sectionIndex + 2 }};"
                        >
                            @include('safety.lototo._board-supervisor', [
                                'supervisorSlots' => $supervisorSlots,
                                'supervisorRows' => $supervisorRows,
                                'supervisorCols' => $supervisorCols,
                                'activeActivities' => $activeActivities,
                                'pendingFollowUps' => $pendingFollowUps,
                            ])
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
