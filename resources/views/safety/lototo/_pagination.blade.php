{{-- Pagination of the LOTOTO Activity pages (10 per page): Previous, page numbers and Next on every screen size, and the
     range being shown. Links keep the query string (board, and any filter) because the controllers call withQueryString(). --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination LOTOTO Activity" class="flex flex-col items-center gap-2 sm:flex-row sm:justify-between" data-activity-pagination>
        <p class="text-xs text-neutral-600">
            Menampilkan <span class="font-semibold">{{ $paginator->firstItem() }}</span>–<span class="font-semibold">{{ $paginator->lastItem() }}</span>
            dari <span class="font-semibold">{{ $paginator->total() }}</span> aktivitas · Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
        </p>
        <ul class="flex flex-wrap items-center justify-center gap-1 text-sm">
            @if ($paginator->onFirstPage())
                <li><span aria-disabled="true" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-neutral-200 px-3 text-neutral-400">Previous</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-neutral-300 px-3 font-semibold text-neutral-800 hover:bg-neutral-100">Previous</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="inline-flex min-h-9 min-w-6 items-center justify-center text-neutral-500">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page === $paginator->currentPage())
                                <span aria-current="page" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md bg-neutral-900 px-2 font-bold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="Halaman {{ $page }}" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-neutral-300 px-2 font-semibold text-neutral-800 hover:bg-neutral-100">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-neutral-300 px-3 font-semibold text-neutral-800 hover:bg-neutral-100">Next</a></li>
            @else
                <li><span aria-disabled="true" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-neutral-200 px-3 text-neutral-400">Next</span></li>
            @endif
        </ul>
    </nav>
@endif
