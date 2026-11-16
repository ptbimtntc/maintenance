                @php
                    // Something to show: a named tool, or a slot of another kind placed in this band.
                    $hasToolData = $toolSlots->contains(fn ($slot) => $slot->category !== 'tool' || ($slot->is_enabled && (filled($slot->tool_label) || $slot->tool)));
                @endphp
                <div
                    class="lototo-group lototo-other-group flex min-w-0 border-b-2 border-neutral-700"
                    style="--lototo-row-count: {{ $toolRows }}; --lototo-grid-rows: {{ $toolRows }}; order: {{ $sectionOrder }};"
                >
                    <div class="lototo-vertical-label lototo-vertical-label--other"><span>OTHER</span>
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
