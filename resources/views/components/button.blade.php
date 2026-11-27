@props([
    'variant' => 'primary',
    'size' => 'md',
])

@php
$variants = [
    'primary' => 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:ring-brand-500',
    'secondary' => 'border border-neutral-300 bg-white text-neutral-700 hover:border-accent-400 hover:bg-accent-50 hover:text-accent-700 focus-visible:ring-accent-500',
    'soft' => 'border border-brand-300 bg-brand-50 text-brand-700 hover:bg-brand-100 focus-visible:ring-brand-500',
    'danger' => 'bg-danger-600 text-white hover:bg-danger-700 focus-visible:ring-danger-500',
];

$sizes = [
    'xs' => 'px-3 py-1 text-xs',
    'sm' => 'px-3 py-1.5 text-sm',
    'md' => 'px-4 py-2 text-sm',
];

$classes = 'glass-btn glass-btn--'.(isset($variants[$variant]) ? $variant : 'primary').' inline-flex items-center justify-center gap-1.5 rounded-md font-medium shadow-sm transition duration-150 hover:shadow active:scale-[0.97] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 '
    .($variants[$variant] ?? $variants['primary']).' '
    .($sizes[$size] ?? $sizes['md']);
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
