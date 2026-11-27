@props([
    'title',
    'subtitle' => null,
    'href' => null,
    'hrefLabel' => 'View all',
    'empty' => null,
])

{{-- Shared dashboard panel: title row (with optional "view all" link or
     custom `action` slot) plus either the slot or an empty-state message. --}}
<section data-reveal {{ $attributes->class(['glass-card flex flex-col rounded-lg border border-neutral-200 bg-white p-4 shadow-sm transition-shadow duration-200 hover:shadow-md']) }}>
    <header class="mb-3 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-neutral-900">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-0.5 text-[11px] text-neutral-400">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($action)
            {{ $action }}
        @elseif ($href)
            <a href="{{ $href }}" class="shrink-0 text-xs font-medium text-accent-600 hover:text-accent-700 hover:underline">{{ $hrefLabel }} &rarr;</a>
        @endisset
    </header>

    @if (filled($empty))
        <div class="flex flex-1 flex-col items-center justify-center gap-2 py-8 text-center">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-100 text-neutral-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z" />
                </svg>
            </span>
            <p class="text-sm text-neutral-500">{{ $empty }}</p>
        </div>
    @else
        {{ $slot }}
    @endif
</section>
