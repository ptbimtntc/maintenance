@props(['label' => '', 'active' => false])

{{--
    A collapsible sidebar section: closed by default so the sidebar doesn't
    grow tall with every menu's items always visible, opened by hovering OR
    clicking its header (closes again on mouse-leave), and auto-open on load
    when the current page lives inside it so users always see where they are.
--}}
<div x-data="{ open: {{ $active ? 'true' : 'false' }} }" @mouseleave="open = false" class="rounded-md">
    <button type="button"
            @click="open = true"
            @mouseenter="open = true"
            class="flex w-full items-center justify-between rounded-md px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-500 hover:text-neutral-300"
            :class="{ 'text-neutral-300': open }">
        <span>{{ $label }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 transition-transform duration-150" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div x-show="open" x-transition.opacity.duration.150ms x-cloak class="space-y-1 pb-1 pt-1">
        {{ $slot }}
    </div>
</div>
