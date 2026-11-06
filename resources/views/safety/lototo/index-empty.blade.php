<x-app-layout>
    <x-slot name="header">LOTOTO</x-slot>

    <div class="lototo-page">
        <nav class="lototo-board-actions" aria-label="LOTOTO board controls">
            <span class="lototo-board-select-empty">Tidak ada board aktif yang dapat diakses</span>
        </nav>
        @if (session('status'))
            <div role="status" class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">{{ session('status') }}</div>
        @else
            <div role="status" class="rounded-md border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-700">Saat ini tidak ada board LOTOTO aktif yang dapat Anda akses.</div>
        @endif
    </div>
</x-app-layout>
