@props(['label' => '', 'active' => false, 'icon' => null])

{{--
    A collapsible sidebar section. Opens/closes on click only (no hover), the
    choice is remembered per group in localStorage, and a group that contains
    the current page is always open. In the collapsed rail the header is hidden
    and every item is shown as an icon, separated from the previous group by a
    divider. Must live inside the `sidebar` Alpine scope (provides `rail`).
--}}
<div x-data="navGroup(@js(\Illuminate\Support\Str::slug($label)), @js((bool) $active))"
     data-nav-group
     data-label="{{ $label }}"
     :class="rail ? 'mt-2 border-t border-neutral-800 pt-2' : 'mt-1'">
    <button type="button"
            x-show="! rail"
            @click="toggle()"
            :aria-expanded="open.toString()"
            :aria-controls="id"
            class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm font-semibold transition-colors hover:bg-neutral-800/60 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {{ $active ? 'text-white' : 'text-neutral-200' }}">
        @if ($icon)
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 {{ $active ? 'text-brand-400' : 'text-neutral-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
            </svg>
        @endif
        <span class="flex-1 truncate text-left" title="{{ $label }}">{{ $label }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 transition-transform duration-150" :class="{ '-rotate-90': ! open }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    {{-- Sub-items sit under a vertical guide line, indented to line up with the header icon. --}}
    <div :id="id" class="nav-group-panel" :class="{ 'is-closed': ! (open || rail) }">
        <div>
            <div class="space-y-0.5 pb-1 pt-0.5"
                 :class="rail ? '' : 'ml-[1.375rem] border-l border-neutral-700/70 pl-2'">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
