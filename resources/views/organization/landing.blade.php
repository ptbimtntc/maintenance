<x-app-layout>
    <x-slot name="header">Organization &amp; Master Data</x-slot>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($types as $slug => $config)
            <a href="{{ route('organization.index', $slug) }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:border-slate-400 hover:shadow-sm">
                <p class="font-medium text-gray-900">{{ $config['label'] }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $config['model']::count() }} record(s)</p>
            </a>
        @endforeach
    </div>
</x-app-layout>
