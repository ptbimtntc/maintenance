@props(['title', 'total', 'totalLabel' => 'Total', 'segments' => []])

@php
// $segments: [['label' => ..., 'value' => ..., 'color' => '#hex'], ...]
$sum = collect($segments)->sum('value');
@endphp

<div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
    <h3 class="mb-3 text-sm font-semibold text-neutral-900">{{ $title }}</h3>

    <div class="flex items-center gap-4">
        <div class="relative h-32 w-32 shrink-0">
            <canvas
                data-chart
                data-chart-type="doughnut"
                data-chart-labels="{{ json_encode(collect($segments)->pluck('label')) }}"
                data-chart-values="{{ json_encode(collect($segments)->pluck('value')) }}"
                data-chart-colors="{{ json_encode(collect($segments)->pluck('color')) }}"
            ></canvas>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-lg font-bold text-neutral-900">{{ $total }}</span>
                <span class="text-[10px] text-neutral-500">{{ $totalLabel }}</span>
            </div>
        </div>

        <div class="min-w-0 flex-1 space-y-1.5">
            @foreach ($segments as $segment)
                <div class="flex items-center gap-2 text-xs">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $segment['color'] }}"></span>
                    <span class="min-w-0 flex-1 truncate text-neutral-600">{{ $segment['label'] }}</span>
                    <span class="shrink-0 font-medium text-neutral-900">{{ $segment['value'] }}</span>
                    <span class="w-9 shrink-0 text-right text-neutral-400">{{ $sum > 0 ? round($segment['value'] / $sum * 100) : 0 }}%</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
