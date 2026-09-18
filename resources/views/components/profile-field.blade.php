@props(['label', 'value'])

<div>
    <p class="text-xs font-medium uppercase tracking-wide text-neutral-400">{{ $label }}</p>
    <p class="mt-1 text-sm text-neutral-900">{{ $value !== null && $value !== '' ? $value : '—' }}</p>
</div>
