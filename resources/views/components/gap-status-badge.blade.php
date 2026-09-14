@props(['status'])

@php
$styles = [
    'meets' => ['label' => 'Meets Requirement', 'class' => 'bg-green-100 text-green-800'],
    'exceeds' => ['label' => 'Exceeds Requirement', 'class' => 'bg-blue-100 text-blue-800'],
    'gap' => ['label' => 'Development Required', 'class' => 'bg-red-100 text-red-800'],
    'incomplete' => ['label' => 'Not Assessed', 'class' => 'bg-amber-100 text-amber-800'],
    'no-requirements' => ['label' => 'No Requirements Defined', 'class' => 'bg-gray-100 text-gray-600'],
];
$style = $styles[$status] ?? $styles['no-requirements'];
@endphp

<span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $style['class'] }}">{{ $style['label'] }}</span>
