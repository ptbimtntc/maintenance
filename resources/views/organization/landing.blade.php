@php
// Each group gets one of the two Bekaert brand colors so the sections read
// as distinct blocks at a glance.
$palette = [
    ['bar' => 'bg-brand-500', 'chip' => 'bg-brand-50 text-brand-700', 'hover' => 'hover:border-brand-400 hover:bg-brand-50/40', 'title' => 'text-brand-700'],
    ['bar' => 'bg-accent-500', 'chip' => 'bg-accent-50 text-accent-700', 'hover' => 'hover:border-accent-400 hover:bg-accent-50/40', 'title' => 'text-accent-700'],
];
@endphp

<x-app-layout>
    <x-slot name="header">Organization &amp; Master Data</x-slot>

    <div class="space-y-5">
        @foreach ($groups as $title => $items)
            @php $c = $palette[$loop->index % 2]; @endphp
            <section>
                <div class="mb-2 flex items-center gap-2">
                    <span class="h-4 w-1 rounded-full {{ $c['bar'] }}"></span>
                    <h2 class="text-xs font-semibold uppercase tracking-wide {{ $c['title'] }}">{{ $title }}</h2>
                    <span class="text-xs text-neutral-400">{{ $items->count() }}</span>
                    <div class="h-px flex-1 bg-neutral-200"></div>
                </div>

                <div class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-4">
                    @foreach ($items as $slug => $config)
                        <a href="{{ route('organization.index', $slug) }}"
                           class="group flex items-center justify-between gap-2 rounded-md border border-neutral-200 bg-white px-3 py-2 transition {{ $c['hover'] }}">
                            <span class="truncate text-sm font-medium text-neutral-800">{{ $config['label'] }}</span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $c['chip'] }}">{{ $config['model']::count() }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
