@props(['activeCount' => 0])

@php
$hasActive = $activeCount > 0;
@endphp

<div x-data="{ open: {{ $hasActive ? 'true' : 'false' }} }" class="space-y-1.5">
    <button type="button" @click="open = !open"
            class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium shadow-sm transition
                   {{ $hasActive ? 'border-brand-300 bg-brand-50 text-brand-700 hover:bg-brand-100' : 'border-neutral-300 bg-white text-neutral-600 hover:border-accent-400 hover:text-accent-700 hover:bg-accent-50' }}">
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
        </svg>
        Filters
        @if ($hasActive)
            <span class="flex h-4.5 min-w-[1.125rem] items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-semibold text-white">{{ $activeCount }}</span>
        @endif
        <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0">
        {{ $slot }}
    </div>
</div>
