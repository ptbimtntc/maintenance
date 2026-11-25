@props(['title', 'total', 'totalLabel' => 'Total', 'segments' => []])

@php
// $segments: [['label' => ..., 'value' => ..., 'color' => '#hex'], ...]
$sum = collect($segments)->sum('value');
@endphp

<x-dashboard-card :title="$title">
    <div class="flex flex-1 items-center gap-4">
        <div class="relative h-32 w-32 shrink-0">
            @if ($sum > 0)
                <canvas
                    data-chart
                    data-chart-type="doughnut"
                    data-chart-labels="{{ json_encode(collect($segments)->pluck('label')) }}"
                    data-chart-values="{{ json_encode(collect($segments)->pluck('value')) }}"
                    data-chart-colors="{{ json_encode(collect($segments)->pluck('color')) }}"
                ></canvas>
            @else
                {{-- Chart.js draws nothing when every value is 0; show an empty ring instead. --}}
                <div class="absolute inset-1 rounded-full border-[14px] border-neutral-100"></div>
            @endif
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span data-countup class="text-lg font-bold tabular-nums text-neutral-900">{{ $total }}</span>
                <span class="text-[10px] text-neutral-500">{{ $totalLabel }}</span>
            </div>
        </div>

        <ul class="min-w-0 flex-1 space-y-1.5">
            @foreach ($segments as $segment)
                <li class="flex items-center gap-2 text-xs">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $segment['color'] }}"></span>
                    <span class="min-w-0 flex-1 truncate text-neutral-600" title="{{ $segment['label'] }}">{{ $segment['label'] }}</span>
                    <span class="shrink-0 font-medium text-neutral-900">{{ $segment['value'] }}</span>
                    <span class="w-9 shrink-0 text-right text-neutral-400">{{ $sum > 0 ? round($segment['value'] / $sum * 100) : 0 }}%</span>
                </li>
            @endforeach
        </ul>
    </div>
</x-dashboard-card>
