<x-app-layout>
    <x-slot name="header">Organization &amp; Master Data</x-slot>

    <div class="space-y-8">
        @foreach ($groups as $title => $items)
            <section>
                <div class="mb-3 flex items-center gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ $title }}</h2>
                    <div class="h-px flex-1 bg-neutral-200"></div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $slug => $config)
                        <a href="{{ route('organization.index', $slug) }}" class="rounded-lg border border-neutral-200 bg-white p-5 hover:border-brand-400 hover:shadow-sm">
                            <p class="font-medium text-neutral-900">{{ $config['label'] }}</p>
                            <p class="mt-1 text-sm text-neutral-500">{{ $config['model']::count() }} record(s)</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
