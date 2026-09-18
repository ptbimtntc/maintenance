@props(['href' => '#', 'active' => false, 'disabled' => false, 'icon' => null])

@php
$classes = $disabled
    ? 'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-neutral-500 cursor-not-allowed'
    : ($active
        ? 'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium bg-brand-500 text-white'
        : 'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-neutral-300 hover:bg-neutral-800 hover:text-white transition');
@endphp

@if ($disabled)
    <span class="{{ $classes }}" title="Planned for a future development phase">
        @if ($icon)
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
            </svg>
        @endif
        <span class="flex-1">{{ $slot }}</span>
        <span class="rounded bg-neutral-700 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-300">Soon</span>
    </span>
@else
    <a href="{{ $href }}" class="{{ $classes }}">
        @if ($icon)
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
            </svg>
        @endif
        <span class="flex-1">{{ $slot }}</span>
    </a>
@endif
