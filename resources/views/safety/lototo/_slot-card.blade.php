{{-- One slot on the board, drawn as its category (what it holds) wherever its band ($band) is. A slot in another
     category's band carries data-slot-section and lototo-card--as-{category}, so it keeps its own look there. --}}
@php
    $category = $slot->category;
    $foreign = $category !== $band;
    $asClass = $foreign ? 'lototo-card--as-'.$category : null;
@endphp
@if ($category === 'technician' || $category === 'supervisor')
                <div data-slot-category="{{ $category }}" data-slot-position="{{ $slot->position }}" data-grid-row="{{ $slot->grid_row }}" data-grid-column="{{ $slot->grid_column }}" @if ($foreign) data-slot-section="{{ $band }}" @endif @if ($slot->id) data-slot-id="{{ $slot->id }}" @endif style="grid-column: {{ $slot->grid_column }}; grid-row: {{ $slot->grid_row }};">
                    @include('safety.lototo._employee-slot', [
                        'slot' => $slot,
                        'groupName' => strtoupper($category),
                        'activeActivities' => $activeActivities,
                        'pendingFollowUps' => $pendingFollowUps,
                        'cardClass' => $asClass,
                    ])
                </div>
@elseif ($category === 'spare')
                @php
                    $assignedSpareLock = $slot->spareLock;
                    $spareLock = $slot->is_enabled || $assignedSpareLock?->currentActivity
                        ? $assignedSpareLock
                        : null;
                    // The cell the board view gave this slot (stored grid_row / grid_column).
                    $gridColumn = (int) $slot->grid_column;
                    $gridRow = (int) $slot->grid_row;
                @endphp
                @if (! $spareLock)
                    <div
                        @class(['lototo-card lototo-empty-slot', $asClass => $foreign])
                        data-slot-category="spare" data-slot-position="{{ $slot->position }}" data-grid-row="{{ $slot->grid_row }}" data-grid-column="{{ $slot->grid_column }}" @if ($foreign) data-slot-section="{{ $band }}" @endif @if ($slot->id) data-slot-id="{{ $slot->id }}" @endif
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
                    data-slot-category="spare" data-slot-position="{{ $slot->position }}" data-grid-row="{{ $slot->grid_row }}" data-grid-column="{{ $slot->grid_column }}" @if ($foreign) data-slot-section="{{ $band }}" @endif @if ($slot->id) data-slot-id="{{ $slot->id }}" @endif
                    style="grid-column: {{ $gridColumn }}; grid-row: {{ $gridRow }};"
                    @class([
                        'lototo-card border-r border-b border-neutral-400 text-center transition hover:z-10 hover:shadow-md',
                        'lototo-spare-lock--inactive' => ! $spareLock->is_active,
                        'lototo-active-card lototo-active-card--spare' => $spareActivity !== null,
                        'lototo-lock-card lototo-lock-card--spare' => $spareActivity === null,
                        $asClass => $foreign,
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
@else
                                @php($toolPlacement = ['column' => (int) $slot->grid_column, 'span' => max(1, min(3, (int) ($slot->tool_column_span ?? 1))), 'row' => (int) $slot->grid_row])
                                @php($tool = $slot->tool)
                                @php($toolName = $tool?->name ?? $slot->tool_label)
                                @if ($slot->is_enabled && filled($toolName))
                                    @php($toolOpen = $tool?->openActivity)
                                    <{{ $tool ? 'a' : 'div' }}
                                        data-slot-category="tool" data-slot-position="{{ $slot->position }}" data-grid-row="{{ $slot->grid_row }}" data-grid-column="{{ $slot->grid_column }}" @if ($foreign) data-slot-section="{{ $band }}" @endif @if ($slot->id) data-slot-id="{{ $slot->id }}" @endif
                                        @if ($tool) href="{{ route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $board->id]) }}" @endif
                                        @class([
                                            'lototo-card gap-0.5 border-r border-b border-neutral-400 bg-stone-100 text-[6px] font-bold uppercase tracking-wide text-stone-600',
                                            'lototo-tool-card--in' => $toolOpen,
                                            'lototo-tool-card--inactive' => $tool && ! $tool->is_active,
                                            $asClass => $foreign,
                                        ])
                                        style="grid-column: {{ $toolPlacement['column'] }} / span {{ $toolPlacement['span'] }}; grid-row: {{ $toolPlacement['row'] }};"
                                        aria-label="Other posisi {{ $slot->position }} {{ $toolName }}{{ $toolOpen ? ' IN di '.$toolOpen->location : '' }}"
                                        @if ($toolOpen) title="IN di {{ $toolOpen->location }}" @endif
                                    >
                                        <span class="lototo-position-index">{{ sprintf('%02d', $slot->position) }}</span>
                                        @if ($toolOpen)
                                            <span class="lototo-tool-status">IN</span>
                                        @endif
                                        @if ($tool?->image_path)
                                            <img class="lototo-tool-image" src="{{ route('safety.lototo.tools.image', $tool) }}?v={{ $tool->updated_at?->timestamp }}" alt="" loading="lazy">
                                        @else
                                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94L14.7 6.3z" />
                                            </svg>
                                        @endif
                                        <span class="lototo-tool-name" title="{{ $toolName }}">{{ $toolName }}</span>
                                    </{{ $tool ? 'a' : 'div' }}>
                                @else
                                    <div
                                        @class(['lototo-card lototo-empty-slot border-r border-b border-neutral-400', $asClass => $foreign])
                                        data-slot-category="tool" data-slot-position="{{ $slot->position }}" data-grid-row="{{ $slot->grid_row }}" data-grid-column="{{ $slot->grid_column }}" @if ($foreign) data-slot-section="{{ $band }}" @endif @if ($slot->id) data-slot-id="{{ $slot->id }}" @endif
                                        style="grid-column: {{ $toolPlacement['column'] }} / span {{ $toolPlacement['span'] }}; grid-row: {{ $toolPlacement['row'] }};"
                                        aria-label="Other posisi {{ $slot->position }} kosong"
                                    >
                                        <span class="lototo-position-index">{{ sprintf('%02d', $slot->position) }}</span>
                                        <svg class="h-4 w-4 shrink-0 text-stone-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94L14.7 6.3z" />
                                        </svg>
                                    </div>
                                @endif
@endif
