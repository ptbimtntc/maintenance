@php
$isBlueCollar = $employee->workforce_category === 'BC';
$isIntern = $employee->workforce_category === 'INTERN';
$children = $childrenByParent->get($employee->id, collect());
// Supervisors' teams start collapsed; everyone above them starts expanded.
// A group whose members are all Blue Collar lays out in two columns
// instead of one long row. While a search/filter is active, every node
// starts expanded so the reporting line up to the top is visible without
// the user having to click through each collapsed supervisor.
$isSupervisor = str_contains(strtolower((string) $employee->position?->title), 'supervisor');
$stackChildren = $children->isNotEmpty() && $children->every(fn ($child) => $child->workforce_category === 'BC');
$forceExpand ??= false;
$highlightIds ??= collect();
$startOpen = $forceExpand || ! $isSupervisor;
$isMatch = $highlightIds->contains($employee->id);

// Blue Collar, White Collar Management and Interns each get their own
// card color so the workforce_category is readable at a glance in the
// tree, matching the Workforce Mix legend/chart elsewhere in the app.
$cardClass = match (true) {
    $isBlueCollar => 'border-accent-700 bg-accent-600 text-white',
    $isIntern => 'border-pink-700 bg-pink-500 text-white',
    default => 'border-neutral-200 bg-white text-neutral-900',
};
$avatarClass = match (true) {
    $isBlueCollar => 'bg-accent-800 text-white',
    $isIntern => 'bg-pink-700 text-white',
    default => 'bg-neutral-100 text-neutral-500',
};
$mutedTextClass = match (true) {
    $isBlueCollar => 'text-accent-100',
    $isIntern => 'text-pink-100',
    default => 'text-neutral-500',
};
$photoRingClass = $isBlueCollar || $isIntern ? 'ring-2 ring-white/70' : 'ring-2 ring-neutral-100';
@endphp

<li x-data="{ open: {{ $startOpen ? 'true' : 'false' }} }"
    @org-expand-all.window="open = true" @org-collapse-all.window="open = {{ $isSupervisor ? 'false' : 'true' }}">
    <a href="{{ route('employees.show', ['employee' => $employee, 'from' => 'org-chart']) }}"
       class="org-chart-node inline-flex w-48 flex-col items-center gap-1 rounded-lg border p-3 text-center shadow-sm transition hover:shadow-md
              {{ $cardClass }}
              {{ $isMatch ? 'ring-4 ring-brand-400 ring-offset-2 ring-offset-neutral-100' : '' }}">
        @if ($employee->photo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}"
                 alt="{{ $employee->full_name }}"
                 class="h-14 w-14 rounded-full object-cover {{ $photoRingClass }}">
        @else
            <span class="flex h-14 w-14 items-center justify-center rounded-full text-base font-semibold {{ $avatarClass }}">
                {{ \Illuminate\Support\Str::of($employee->full_name)->explode(' ')->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->take(2)->implode('') }}
            </span>
        @endif

        <span class="text-sm font-semibold leading-tight">{{ $employee->full_name }}</span>
        <span class="text-xs {{ $mutedTextClass }}">{{ $employee->employee_number }}</span>
        <span class="text-xs {{ $mutedTextClass }}">{{ $employee->skillPosition?->name ?? '—' }}</span>
    </a>

    @if ($children->isNotEmpty())
        <button type="button" @click="open = ! open"
                class="relative z-10 mt-2 inline-flex items-center gap-1 rounded-full border border-neutral-300 bg-white px-2.5 py-0.5 text-xs font-medium text-neutral-600 hover:border-brand-400 hover:text-brand-700"
                :aria-expanded="open.toString()">
            <svg class="h-3 w-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
            <span x-text="open ? 'Collapse' : 'Expand'"></span>
            <span class="text-neutral-400">({{ $children->count() }})</span>
        </button>

        <ul x-show="open" x-cloak @class(['org-chart-stack' => $stackChildren])>
            @foreach ($children as $child)
                @include('organization-chart._node', ['employee' => $child, 'childrenByParent' => $childrenByParent, 'highlightIds' => $highlightIds, 'forceExpand' => $forceExpand])
            @endforeach
        </ul>
    @endif
</li>
