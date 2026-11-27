{{-- Ctrl/Cmd+K menu search. Results are collected from the rendered sidebar
     (see resources/js/sidebar.js), so only menus the user may open appear.
     Open it from anywhere with: $dispatch('open-menu-search'). --}}
<div
    x-data="menuSearch"
    x-on:open-menu-search.window="open()"
    x-on:keydown.window="(($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k') && toggleFromShortcut($event)"
    x-on:keydown.escape.window="visible && close()"
    x-show="visible"
    x-cloak
    class="fixed inset-0 z-[60] flex items-start justify-center px-4 pt-[12vh]"
    role="dialog"
    aria-modal="true"
    aria-label="Search menu"
>
    <div
        x-show="visible"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="glass-backdrop fixed inset-0"
        @click="close()"
    ></div>

    <div
        x-show="visible"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="glass-modal relative w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl"
    >
        <div class="flex items-center gap-3 border-b border-neutral-200 px-4">
            <svg class="h-5 w-5 shrink-0 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
                x-ref="input"
                x-model="query"
                @input="active = 0"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="go()"
                type="text"
                placeholder="Search menu…"
                autocomplete="off"
                spellcheck="false"
                aria-label="Search menu"
                class="w-full border-0 bg-transparent py-3.5 text-sm text-neutral-900 placeholder-neutral-400 focus:ring-0"
            >
            <kbd class="hidden shrink-0 rounded border border-neutral-200 px-1.5 py-0.5 text-[10px] font-medium text-neutral-400 sm:block">Esc</kbd>
        </div>

        <ul x-ref="list" class="max-h-80 overflow-y-auto p-2" role="listbox">
            <template x-for="(item, index) in results" :key="item.href">
                <li role="presentation">
                    <a
                        :href="item.href"
                        role="option"
                        :aria-selected="(index === active).toString()"
                        :data-active="(index === active).toString()"
                        @mouseenter="active = index"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm"
                        :class="index === active ? 'bg-brand-50 text-neutral-900' : 'text-neutral-700'"
                    >
                        <svg class="h-5 w-5 shrink-0" :class="index === active ? 'text-brand-600' : 'text-neutral-400'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        <span class="min-w-0 flex-1 truncate font-medium" x-text="item.label"></span>
                        <span x-show="item.current" class="shrink-0 rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-500">Current</span>
                        <span class="shrink-0 text-xs text-neutral-400" x-text="item.group"></span>
                    </a>
                </li>
            </template>
        </ul>

        <p x-show="results.length === 0" class="px-4 py-8 text-center text-sm text-neutral-500">
            No menu matches "<span x-text="query"></span>".
        </p>

        <div class="flex items-center gap-4 border-t border-neutral-100 bg-neutral-50 px-4 py-2 text-[11px] text-neutral-400">
            <span><kbd class="font-medium">↑↓</kbd> navigate</span>
            <span><kbd class="font-medium">Enter</kbd> open</span>
        </div>
    </div>
</div>
