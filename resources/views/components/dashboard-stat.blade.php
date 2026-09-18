@props([
    'label',
    'value',
    'icon' => null,
    'href' => null,
    'color' => 'slate',
    'chartType' => null,
    'chartLabels' => [],
    'chartValues' => [],
    'chartColors' => [],
    'chartLegend' => false,
])

@php
$colorClasses = [
    'slate' => 'bg-neutral-100 text-neutral-600',
    'blue' => 'bg-accent-100 text-accent-600',
    'green' => 'bg-green-100 text-green-600',
    'amber' => 'bg-amber-100 text-amber-600',
    'red' => 'bg-red-100 text-red-600',
    'purple' => 'bg-purple-100 text-purple-600',
][$color] ?? 'bg-neutral-100 text-neutral-600';
@endphp

<div @class([
    'group relative flex flex-col rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition-all duration-150',
    'hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md' => $href,
])>
    @if ($href)
        <a href="{{ $href }}" class="absolute inset-0 z-10 rounded-xl" aria-label="{{ $label }}"></a>
    @endif

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium leading-snug text-neutral-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ $value }}</p>
        </div>
        @if ($icon)
            <span @class([
                'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-transform duration-150',
                'group-hover:scale-105' => $href,
                $colorClasses,
            ])>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
    </div>

    @if ($chartType)
        <div class="relative mt-3 w-full {{ $chartType === 'bar' ? 'h-24' : 'h-16' }}">
            <canvas
                data-chart
                data-chart-type="{{ $chartType }}"
                data-chart-labels="{{ json_encode($chartLabels) }}"
                data-chart-values="{{ json_encode($chartValues) }}"
                data-chart-colors="{{ json_encode($chartColors) }}"
                data-chart-legend="{{ $chartLegend ? 'true' : 'false' }}"
            ></canvas>
        </div>
    @endif

    @if ($href)
        <span class="relative mt-3 inline-flex items-center gap-1 text-xs font-medium text-neutral-400 transition-colors group-hover:text-neutral-600">
            View details
            <svg class="h-3 w-3 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H4a1 1 0 110-2h10.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </span>
    @endif
</div>
