@props(['label', 'value'])

<div>
    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $label }}</p>
    <p class="mt-1 text-sm text-gray-900">{{ $value !== null && $value !== '' ? $value : '—' }}</p>
</div>
