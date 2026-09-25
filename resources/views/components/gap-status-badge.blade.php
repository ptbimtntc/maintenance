@props(['status'])

@php
$styles = [
    'meets' => ['label' => 'Meets Requirement', 'class' => 'bg-success-100 text-success-800'],
    'exceeds' => ['label' => 'Exceeds Requirement', 'class' => 'bg-accent-100 text-accent-800'],
    'gap' => ['label' => 'Development Required', 'class' => 'bg-danger-100 text-danger-800'],
    'incomplete' => ['label' => 'Not Assessed', 'class' => 'bg-warning-100 text-warning-800'],
    'no-requirements' => ['label' => 'No Requirements Defined', 'class' => 'bg-neutral-100 text-neutral-600'],
];
$style = $styles[$status] ?? $styles['no-requirements'];
@endphp

<span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $style['class'] }}">{{ $style['label'] }}</span>
