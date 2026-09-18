@php
$isBlueCollar = $employee->workforce_category === 'BC';
$children = $childrenByParent->get($employee->id, collect());
@endphp

<li>
    <a href="{{ route('employees.show', $employee) }}"
       class="org-chart-node inline-flex w-48 flex-col items-center gap-1 rounded-lg border p-3 text-center shadow-sm transition hover:shadow-md
              {{ $isBlueCollar ? 'border-accent-700 bg-accent-600 text-white' : 'border-neutral-200 bg-white text-neutral-900' }}">
        @if ($employee->photo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}"
                 alt="{{ $employee->full_name }}"
                 class="h-14 w-14 rounded-full object-cover {{ $isBlueCollar ? 'ring-2 ring-white/70' : 'ring-2 ring-neutral-100' }}">
        @else
            <span @class([
                'flex h-14 w-14 items-center justify-center rounded-full text-base font-semibold',
                'bg-accent-800 text-white' => $isBlueCollar,
                'bg-neutral-100 text-neutral-500' => ! $isBlueCollar,
            ])>
                {{ \Illuminate\Support\Str::of($employee->full_name)->explode(' ')->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->take(2)->implode('') }}
            </span>
        @endif

        <span class="text-sm font-semibold leading-tight">{{ $employee->full_name }}</span>
        <span @class(['text-xs', 'text-accent-100' => $isBlueCollar, 'text-neutral-500' => ! $isBlueCollar])>{{ $employee->employee_number }}</span>
        <span @class(['text-xs', 'text-accent-100' => $isBlueCollar, 'text-neutral-500' => ! $isBlueCollar])>{{ $employee->skillPosition?->name ?? '—' }}</span>
    </a>

    @if ($children->isNotEmpty())
        <ul>
            @foreach ($children as $child)
                @include('organization-chart._node', ['employee' => $child, 'childrenByParent' => $childrenByParent])
            @endforeach
        </ul>
    @endif
</li>
