<x-app-layout>
    <x-slot name="header">News</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-sm text-neutral-500">{{ $newsItems->total() }} news item(s). Published items appear in the dashboard carousel.</p>
            <a href="{{ route('news.create') }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                Add News
            </a>
        </div>

        <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Image</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Title</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Template</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Created</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($newsItems as $item)
                        <tr>
                            <td class="px-3 py-2">
                                @if ($item->image_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path) }}" alt="" class="h-10 w-16 rounded object-cover">
                                @else
                                    <span class="text-neutral-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $item->title }}</td>
                            <td class="px-3 py-2 text-neutral-600 capitalize">{{ $item->template }}</td>
                            <td class="px-3 py-2">
                                @if (!$item->is_published)
                                    <span class="inline-flex rounded-full bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-500">Draft</span>
                                @elseif ($item->isExpired())
                                    <span class="inline-flex rounded-full bg-warning-100 px-2 py-1 text-xs font-medium text-warning-800">Expired</span>
                                @else
                                    <span class="inline-flex rounded-full bg-success-100 px-2 py-1 text-xs font-medium text-success-800">Published</span>
                                @endif
                                @if ($item->expires_at)
                                    <span class="block text-xs text-neutral-400">until {{ $item->expires_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-neutral-600">{{ $item->created_at->format('d M Y') }}</td>
                            <td class="px-3 py-2 text-right space-x-2">
                                <a href="{{ route('news.edit', $item) }}" class="text-neutral-600 hover:underline">Edit</a>
                                <form action="{{ route('news.destroy', $item) }}" method="POST" class="inline" onsubmit="return confirm('Delete this news item?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-danger-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">No news yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $newsItems->links() }}
    </div>
</x-app-layout>
