                @php
                    $hasToolData = $toolSlots->contains(fn ($slot) => $slot->is_enabled && (filled($slot->tool_label) || $slot->tool));
                @endphp
                <div
                    class="lototo-group lototo-other-group flex min-w-0 border-b-2 border-neutral-700"
                    style="--lototo-row-count: {{ $toolRows }}; --lototo-grid-rows: {{ $toolRows }}; order: {{ $sectionOrder }};"
                >
                    <div class="lototo-vertical-label lototo-vertical-label--other">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94L14.7 6.3z"/></svg>
                        <span>OTHER</span>
                    </div>
                    <div class="lototo-group-content min-w-0 flex-1">
                        @if (! $hasToolData)
                            <p class="lototo-empty-message">Belum ada data LOTOTO untuk bagian ini.</p>
                        @else
                        <div class="lototo-other-grid" style="--lototo-grid-rows: {{ $toolRows }};">
                            @foreach ($toolSlots as $slot)
                                @php($toolPlacement = $toolPlacements[$slot->position] ?? ['column' => 1, 'span' => 1, 'row' => 1])
                                @php($tool = $slot->tool)
                                @php($toolName = $tool?->name ?? $slot->tool_label)
                                @if ($slot->is_enabled && filled($toolName))
                                    @php($toolOpen = $tool?->openActivity)
                                    <{{ $tool ? 'a' : 'div' }}
                                        @if ($tool) href="{{ route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $board->id]) }}" @endif
                                        @class([
                                            'lototo-card gap-0.5 border-r border-b border-neutral-400 bg-stone-100 text-[6px] font-bold uppercase tracking-wide text-stone-600',
                                            'lototo-tool-card--in' => $toolOpen,
                                            'lototo-tool-card--inactive' => $tool && ! $tool->is_active,
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
                                        class="lototo-card lototo-empty-slot border-r border-b border-neutral-400"
                                        style="grid-column: {{ $toolPlacement['column'] }} / span {{ $toolPlacement['span'] }}; grid-row: {{ $toolPlacement['row'] }};"
                                        aria-label="Other posisi {{ $slot->position }} kosong"
                                    >
                                        <span class="lototo-position-index">{{ sprintf('%02d', $slot->position) }}</span>
                                        <svg class="h-4 w-4 shrink-0 text-stone-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94L14.7 6.3z" />
                                        </svg>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
