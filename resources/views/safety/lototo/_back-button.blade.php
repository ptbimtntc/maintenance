<a href="{{ $href }}" class="lototo-back-button" aria-label="{{ $label ?? 'Kembali' }}" title="{{ $label ?? 'Kembali' }}">
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M19 12H5m0 0 7 7m-7-7 7-7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span class="sr-only">{{ $label ?? 'Kembali' }}</span>
</a>
