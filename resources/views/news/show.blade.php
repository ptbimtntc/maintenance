@php
    $imageUrl = fn ($item) => \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path);
    $readMinutes = max(1, (int) ceil(str_word_count(strip_tags($news->body)) / 200));
@endphp

<x-app-layout>
    <x-slot name="header">News</x-slot>

    {{-- data-motion opts this page into scroll reveal / parallax (resources/js/motion.js). --}}
    <div class="mx-auto max-w-3xl space-y-6" data-motion>
        <a href="{{ route('dashboard') }}" class="group inline-flex items-center gap-1.5 text-sm text-neutral-500 transition-colors hover:text-neutral-800">
            <svg class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L5.414 9H16a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
            Back to Dashboard
        </a>

        @if (session('status'))
            <div data-reveal class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <article id="news-article" class="relative overflow-clip rounded-2xl border border-neutral-200 bg-white shadow-md">
            {{-- Reading progress: fills as the article scrolls past. --}}
            <div class="sticky top-0 z-10 h-1 w-full bg-transparent">
                <div data-read-progress="#news-article" class="h-full origin-left scale-x-0 bg-gradient-to-r from-brand-500 via-warning-400 to-accent-500"></div>
            </div>

            <header class="relative -mt-1 overflow-hidden {{ $news->image_path ? 'h-64 sm:h-96' : '' }}">
                @if ($news->image_path)
                    <img data-parallax src="{{ $imageUrl($news) }}" alt="{{ $news->title }}" class="absolute inset-x-0 -top-[10%] h-[120%] w-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                @else
                    <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-br from-brand-600 via-brand-500 to-warning-500">
                        <div class="motion-blob absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/15 blur-2xl"></div>
                        <div class="motion-blob motion-blob--slow absolute -bottom-20 left-10 h-48 w-48 rounded-full bg-accent-400/30 blur-2xl"></div>
                    </div>
                @endif

                <div class="{{ $news->image_path ? 'absolute inset-x-0 bottom-0' : 'relative' }} p-6 sm:p-8">
                    <div class="motion-enter flex flex-wrap items-center gap-2 text-xs text-white/80">
                        <span class="rounded-full bg-white/15 px-2.5 py-1 font-medium text-white ring-1 ring-white/25 backdrop-blur-sm">News</span>
                        @if ($news->requires_acknowledgement)
                            <span class="rounded-full bg-warning-400/90 px-2.5 py-1 font-semibold text-warning-900">Confirmation required</span>
                        @endif
                    </div>
                    <h1 class="motion-enter mt-3 text-2xl font-bold leading-tight text-white sm:text-3xl" style="--d: 80ms">{{ $news->title }}</h1>
                    <p class="motion-enter mt-2 text-xs text-white/75" style="--d: 160ms">
                        {{ $news->created_at->format('d M Y') }}
                        @if ($news->createdBy)
                            &middot; {{ $news->createdBy->name }}
                        @endif
                        &middot; {{ $readMinutes }} min read
                    </p>
                </div>
            </header>

            <div data-reveal class="p-6 sm:p-8">
                <div class="prose prose-sm max-w-none leading-relaxed text-neutral-700 sm:prose-base prose-headings:text-neutral-900 prose-a:text-brand-700 prose-img:rounded-lg">{!! $news->body !!}</div>
            </div>
        </article>

        @if ($ownAcknowledgement)
            <div data-reveal class="rounded-2xl border p-5 shadow-md {{ $ownAcknowledgement->acknowledged_at ? 'border-success-200 bg-success-50' : 'border-warning-300 bg-warning-50' }}">
                @if ($ownAcknowledgement->acknowledged_at)
                    <div class="flex items-start gap-3">
                        <svg class="h-6 w-6 shrink-0 text-success-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9.5" class="motion-draw" style="--dash: 64; animation-delay: 300ms; animation-duration: 700ms" />
                            <path d="M8 12.5l2.7 2.7L16.2 9.5" class="motion-draw" style="--dash: 14; animation-delay: 850ms; animation-duration: 400ms" />
                        </svg>
                        <p class="text-sm font-medium text-success-800">
                            You confirmed that you have read and understood this announcement on
                            {{ $ownAcknowledgement->acknowledged_at->format('d M Y, H:i:s') }}.
                        </p>
                    </div>
                @else
                    <form method="POST" action="{{ route('news.acknowledge', $news) }}" x-data="{ agreed: false }" class="space-y-3">
                        @csrf
                        <p class="text-sm font-semibold text-warning-800">This announcement requires your confirmation.</p>
                        <label class="flex items-start gap-2 text-sm text-neutral-700">
                            <input type="checkbox" x-model="agreed" class="mt-0.5 rounded border-neutral-300">
                            <span>I have read and understood this announcement.</span>
                        </label>
                        <x-button type="submit" ::disabled="!agreed">Confirm</x-button>
                    </form>
                @endif
            </div>
        @endif

        @if ($news->requires_acknowledgement && auth()->user()->hasPermissionTo(\App\Enums\PermissionName::ManageNews->value))
            <div data-reveal class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-md">
                <div class="border-b border-neutral-200 px-4 py-3 text-sm font-semibold text-neutral-900">
                    Confirmations ({{ $acknowledgements->whereNotNull('acknowledged_at')->count() }} of {{ $acknowledgements->count() }})
                </div>
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-brand-50 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">
                        <tr>
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Confirmed At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($acknowledgements as $row)
                            <tr>
                                <td class="px-4 py-2 text-neutral-900">{{ $row->user->name }} <span class="text-xs text-neutral-400">{{ $row->user->email }}</span></td>
                                <td class="px-4 py-2">
                                    @if ($row->acknowledged_at)
                                        <span class="inline-flex rounded-full bg-success-100 px-2 py-1 text-xs font-medium text-success-800">Confirmed</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-warning-100 px-2 py-1 text-xs font-medium text-warning-800">Pending</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-neutral-600">{{ $row->acknowledged_at?->format('d M Y, H:i:s') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($moreNews->isNotEmpty())
            <section aria-labelledby="more-news" class="space-y-3 pt-4">
                <h2 data-reveal id="more-news" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">More news</h2>
                <div @class(['grid grid-cols-1 gap-4', $moreNews->count() >= 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2'])>
                    @foreach ($moreNews as $item)
                        <a data-reveal href="{{ route('news.show', $item) }}" class="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
                            <div class="relative h-32 overflow-hidden">
                                @if ($item->image_path)
                                    <img src="{{ $imageUrl($item) }}" alt="" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                                @else
                                    <div class="h-full w-full bg-gradient-to-br from-brand-100 to-brand-300 transition-transform duration-500 group-hover:scale-105"></div>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col p-4">
                                <h3 class="line-clamp-2 text-sm font-semibold text-neutral-900 group-hover:text-brand-700">{{ $item->title }}</h3>
                                <p class="mt-1 line-clamp-2 text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit(strip_tags($item->body), 100) }}</p>
                                <p class="mt-auto pt-3 text-[11px] text-neutral-400">{{ $item->created_at->format('d M Y') }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
