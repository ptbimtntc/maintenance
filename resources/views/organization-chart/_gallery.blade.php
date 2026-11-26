{{--
    Organization Chart "Gallery" view: one team at a time. The leader card
    and their reporting line (breadcrumb) sit above a 3D photo stack of their
    direct reports (x-people-gallery). Cards of people who lead a team of
    their own carry a "Team n" badge and open that team; everyone else opens
    their profile. Data: OrganizationChartController::galleryTeam().
--}}
@php
    $leader = $gallery['leader'];
    $teamSizes = $gallery['teamSizes'];
    $teamUrl = fn ($employee = null) => route('organization-chart.index', $employee ? $baseQuery + ['leader' => $employee->id] : $baseQuery);

    // The Plant Manager has no employee profile (hidden from the Employees
    // module): their card links to the Plant Manager menu, or nowhere.
    $profileUrl = function ($employee) {
        if ($employee->skillPosition?->name !== \App\Models\SkillPosition::PLANT_MANAGER) {
            return route('employees.show', ['employee' => $employee, 'from' => 'org-chart']);
        }

        return auth()->user()->can('managePlantManager', \App\Models\Employee::class) ? route('plant-manager.edit') : null;
    };
    $cardUrl = fn ($employee) => $teamSizes->has($employee->id) ? $teamUrl($employee) : $profileUrl($employee);
    $roleOf = fn ($employee) => $employee->skillPosition?->name ?? $employee->position?->title;
    $hasTopLevel = $roots->count() > 1;
    $upUrl = match (true) {
        $leader === null => null,
        $gallery['trail']->isNotEmpty() => $teamUrl($gallery['trail']->last()),
        $hasTopLevel => $teamUrl(),
        default => null,
    };
@endphp

{{-- Reporting line --}}
@if (! $gallery['search'] && ($leader || $hasTopLevel))
    <nav aria-label="Reporting line" class="flex flex-wrap items-center gap-1 text-sm">
        @if ($hasTopLevel)
            @if ($leader)
                <a href="{{ $teamUrl() }}" class="rounded-md px-2 py-1 text-neutral-500 transition hover:bg-white hover:text-brand-700">Organization</a>
            @else
                <span class="rounded-md px-2 py-1 font-semibold text-neutral-900" aria-current="page">Organization</span>
            @endif
        @endif
        @foreach ($gallery['trail'] as $ancestor)
            @if ($hasTopLevel || ! $loop->first)
                <svg class="h-4 w-4 text-neutral-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
            @endif
            <a href="{{ $teamUrl($ancestor) }}" class="rounded-md px-2 py-1 text-neutral-500 transition hover:bg-white hover:text-brand-700">{{ $ancestor->full_name }}</a>
        @endforeach
        @if ($leader)
            @if ($hasTopLevel || $gallery['trail']->isNotEmpty())
                <svg class="h-4 w-4 text-neutral-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
            @endif
            <span class="rounded-md px-2 py-1 font-semibold text-neutral-900" aria-current="page">{{ $leader->full_name }}</span>
        @endif
    </nav>
@endif

{{-- Leader / heading --}}
@if ($gallery['search'])
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-neutral-200 bg-white px-5 py-4 shadow-sm">
        <p class="text-sm text-neutral-700">
            <span class="font-semibold text-neutral-900">{{ $gallery['members']->count() }} {{ Str::plural('match', $gallery['members']->count()) }}</span>
            for “{{ $filters['search'] }}”
        </p>
        <x-button variant="secondary" size="sm" href="{{ route('organization-chart.index', array_merge($baseQuery, ['search' => ''])) }}">Clear search</x-button>
    </div>
@elseif ($leader)
    @php
        $leaderPhoto = \App\Support\LototoEmployeePhoto::url($leader, 'employees.photo');
        $leaderProfile = $profileUrl($leader);
        $trades = $teamTrades->get($leader->id);
    @endphp
    <div class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-brand-50 via-white to-accent-50 p-5 shadow-sm sm:p-6">
        <div aria-hidden="true" class="motion-blob pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-brand-200/40 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center">
            <div class="motion-enter h-24 w-20 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-brand-100 to-accent-100 shadow-lg ring-4 ring-white sm:h-28 sm:w-24">
                @if ($leaderPhoto)
                    <img src="{{ $leaderPhoto }}" alt="{{ $leader->full_name }}" class="h-full w-full object-cover">
                @else
                    <span class="flex h-full w-full items-center justify-center text-2xl font-bold text-brand-700">{{ Str::of($leader->full_name)->explode(' ')->filter()->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->take(2)->implode('') }}</span>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <p class="motion-enter text-xs font-semibold uppercase tracking-wider text-brand-600" style="--d: 60ms">Team of</p>
                <h2 class="motion-enter truncate text-xl font-bold text-neutral-900 sm:text-2xl" style="--d: 120ms">{{ $leader->full_name }}</h2>
                <p class="motion-enter mt-0.5 text-sm text-neutral-500" style="--d: 180ms">
                    <span class="font-medium text-brand-700">{{ $leader->employee_number }}</span>
                    @if ($roleOf($leader)) · {{ $roleOf($leader) }} @endif
                </p>
                <div class="motion-enter mt-3 flex flex-wrap gap-2 text-xs" style="--d: 240ms">
                    <span class="rounded-full bg-white px-2.5 py-1 font-semibold text-neutral-700 shadow-sm ring-1 ring-neutral-200">{{ $teamSizes->get($leader->id) }} direct {{ Str::plural('report', $teamSizes->get($leader->id)) }}</span>
                    @if ($trades && ($trades['M'] || $trades['E']))
                        <span class="rounded-full bg-white px-2.5 py-1 font-semibold text-accent-700 shadow-sm ring-1 ring-neutral-200" title="Blue Collar direct reports: Mechanic / Electric">M {{ $trades['M'] }} · E {{ $trades['E'] }}</span>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 gap-2 sm:flex-col">
                @if ($leaderProfile)
                    <x-button variant="secondary" size="sm" href="{{ $leaderProfile }}">Open profile</x-button>
                @endif
                @if ($upUrl)
                    <x-button variant="secondary" size="sm" href="{{ $upUrl }}">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" /></svg>
                        Up one level
                    </x-button>
                @endif
            </div>
        </div>
    </div>
@endif

<div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-1 text-xs text-neutral-500">
    <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-4 rounded-full bg-accent-600"></span>Blue Collar</span>
    <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-4 rounded-full bg-neutral-400"></span>White Collar Mgmt</span>
    <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-4 rounded-full bg-pink-500"></span>Internship</span>
    <span class="inline-flex items-center gap-1.5"><span class="rounded-full bg-brand-600 px-1.5 py-px text-[10px] font-bold text-white">Team n</span>click to open their team</span>
</div>

<x-people-gallery
    id="org-gallery"
    label="Team photo gallery"
    :employees="$gallery['members']"
    :empty="$filtered ? 'No employees match your search or filters.' : 'No employees to show yet.'"
    :href="$cardUrl"
    :subtitle="$roleOf"
    :team-sizes="$teamSizes"
    :highlight-ids="$gallery['search'] ? collect() : $highlightIds"
    stripe
/>
