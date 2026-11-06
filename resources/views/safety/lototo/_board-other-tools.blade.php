                @php
                    // Something to show: a named tool, or a slot of another kind placed in this band.
                    $hasToolData = $toolSlots->contains(fn ($slot) => $slot->category !== 'tool' || ($slot->is_enabled && (filled($slot->tool_label) || $slot->tool)));
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
                            <p class="lototo-empty-message" @if ($canEditBoard ?? false) data-editor-hide @endif>Belum ada data LOTOTO untuk bagian ini.</p>
                        @endif
                        {{-- An empty Other section still has slots; Edit Mode reveals them so they can be filled. --}}
                        @if ($hasToolData || ($canEditBoard ?? false))
                        <div class="lototo-other-grid" style="--lototo-grid-rows: {{ $toolRows }}; --lototo-section-columns: {{ $toolColumns }};" @if (! $hasToolData) hidden data-editor-reveal @endif>
                            @foreach ($toolSlots as $slot)
                                @include('safety.lototo._slot-card', ['slot' => $slot, 'band' => 'tool'])
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
