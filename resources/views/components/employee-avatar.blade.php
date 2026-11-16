{{-- An employee's photo, or the initials when there is no photo / the file is missing / the image fails to load. The photo is
     streamed by the application (employees.photo, or the signed copy for public pages), so it does not depend on the
     public/storage link. The caller gives the classes (size, shape) for the image and for the initials, so both occupy the
     same box; object-cover keeps a photo of any proportion from being squashed. --}}
@props([
    'employee',
    'imgClass' => 'h-10 w-10 shrink-0 rounded-full object-cover',
    'fallbackClass' => 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-xs font-semibold text-neutral-500',
    'letters' => 2,
    'signed' => false,
])
@php
    $photoUrl = $signed
        ? \App\Support\LototoEmployeePhoto::signedUrl($employee)
        : \App\Support\LototoEmployeePhoto::url($employee, 'employees.photo');
    $initials = \Illuminate\Support\Str::of((string) $employee->full_name)->explode(' ')->filter()
        ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->take($letters)->implode('');
@endphp
@if ($photoUrl)
    <img src="{{ $photoUrl }}" alt="{{ $employee->full_name }}" loading="lazy" decoding="async" class="{{ $imgClass }}"
         data-initials="{{ $initials }}" data-fallback-class="{{ $fallbackClass }}"
         onerror="var s=document.createElement('span');s.className=this.dataset.fallbackClass;s.textContent=this.dataset.initials;this.replaceWith(s)">
@else
    <span class="{{ $fallbackClass }}" title="{{ $employee->full_name }}">{{ $initials }}</span>
@endif
