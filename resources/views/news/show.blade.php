<x-app-layout>
    <x-slot name="header">News</x-slot>

    <div class="mx-auto max-w-3xl space-y-4">
        <a href="{{ route('dashboard') }}" class="text-sm text-neutral-500 hover:underline">&larr; Back to Dashboard</a>

        <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-md">
            @if ($news->image_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($news->image_path) }}" alt="{{ $news->title }}" class="max-h-96 w-full object-cover">
            @endif

            <div class="p-6">
                <h1 class="text-xl font-semibold text-neutral-900">{{ $news->title }}</h1>
                <p class="mt-1 text-xs text-neutral-400">
                    {{ $news->created_at->format('d M Y') }}
                    @if ($news->createdBy)
                        &middot; {{ $news->createdBy->name }}
                    @endif
                </p>
                <div class="mt-4 whitespace-pre-line text-sm leading-relaxed text-neutral-700">{{ $news->body }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
