@props(['href' => '#', 'active' => false, 'disabled' => false, 'icon' => null])

@php
$classes = $disabled
    ? 'group flex items-center gap-2.5 rounded-md border-l-2 border-transparent py-2 pl-2.5 pr-3 text-sm font-medium text-neutral-500 cursor-not-allowed'
    : ($active
        ? 'group flex items-center gap-2.5 rounded-md border-l-2 border-brand-500 bg-brand-500/15 py-2 pl-2.5 pr-3 text-sm font-semibold text-white transition-all duration-150'
        : 'group flex items-center gap-2.5 rounded-md border-l-2 border-transparent py-2 pl-2.5 pr-3 text-sm font-medium text-neutral-300 transition-all duration-150 hover:border-neutral-700 hover:bg-neutral-800 hover:text-white');

$iconWrapClasses = $disabled
    ? 'flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-neutral-500'
    : ($active
        ? 'flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-brand-500 text-white shadow-sm transition-all duration-150'
        : 'flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-neutral-400 transition-all duration-150 group-hover:bg-neutral-700 group-hover:text-white');
@endphp

@if ($disabled)
    <span class="{{ $classes }}" title="Planned for a future development phase">
        @if ($icon)
            <span class="{{ $iconWrapClasses }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
        <span class="flex-1">{{ $slot }}</span>
        <span class="rounded bg-neutral-700 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-300">Soon</span>
    </span>
@else
    <a href="{{ $href }}" class="{{ $classes }}">
        @if ($icon)
            <span class="{{ $iconWrapClasses }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
        <span class="flex-1">{{ $slot }}</span>
    </a>
@endif
