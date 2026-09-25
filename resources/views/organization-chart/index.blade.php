<x-app-layout>
    <x-slot name="header">Organization Chart</x-slot>

    <style>
        /* Classic pure-CSS org-chart tree: each <li> is a node plus its
           subtree, connected to its parent/siblings with border lines
           instead of an image or JS charting library. */
        .org-chart-tree, .org-chart-tree ul {
            list-style: none;
            margin: 0;
            padding-top: 24px;
            position: relative;
        }
        .org-chart-tree { padding-top: 0; display: inline-flex; }
        .org-chart-tree ul { display: flex; }
        .org-chart-tree li {
            position: relative;
            padding: 24px 12px 0 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .org-chart-tree li::before,
        .org-chart-tree li::after {
            content: '';
            position: absolute;
            top: 0;
            right: 50%;
            border-top: 2px solid #cbd5e1;
            width: 50%;
            height: 24px;
        }
        .org-chart-tree li::after { right: auto; left: 50%; border-left: 2px solid #cbd5e1; }
        .org-chart-tree li:only-child::before, .org-chart-tree li:only-child::after { display: none; }
        .org-chart-tree li:only-child { padding-top: 0; }
        .org-chart-tree li:first-child::before { border: 0 none; }
        .org-chart-tree li:last-child::after { border: 0 none; }
        .org-chart-tree li:last-child::before { border-right: 2px solid #cbd5e1; border-radius: 0 6px 0 0; }
        .org-chart-tree li:first-child::after { border-radius: 6px 0 0 0; }
        .org-chart-tree > li { padding-top: 0; }
        .org-chart-tree > li::before, .org-chart-tree > li::after { display: none; }
        /* An all-Blue-Collar group: two columns under a single vertical line
           instead of one long row of siblings. */
        .org-chart-tree ul.org-chart-stack {
            display: grid;
            grid-template-columns: repeat(2, max-content);
            justify-content: center;
            padding-top: 16px;
        }
        .org-chart-tree ul.org-chart-stack > li { padding-top: 12px; }
        .org-chart-tree ul.org-chart-stack > li::before,
        .org-chart-tree ul.org-chart-stack > li::after { display: none; }
        .org-chart-tree ul::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            border-left: 2px solid #cbd5e1;
            width: 0;
            height: 24px;
        }
    </style>

    @php
        $activeFieldClass = 'border-brand-300 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200 shadow-sm';
        $defaultFieldClass = 'border-neutral-300 hover:border-accent-400';
        $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
    @endphp

    <div class="space-y-4">
        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" action="{{ route('organization-chart.index') }}" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or NIK..."
                       class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 sm:col-span-2 {{ filled($filters['search'] ?? null) ? $activeFieldClass : $defaultFieldClass }}" />

                <select name="business_unit_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['business_unit_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Business Units</option>
                    @foreach ($businessUnits as $businessUnit)
                        <option value="{{ $businessUnit->id }}" @selected(($filters['business_unit_id'] ?? null) == $businessUnit->id)>{{ $businessUnit->name }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Filter</button>
                    <a href="{{ route('organization-chart.index') }}" class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 shadow-sm transition hover:border-accent-400 hover:bg-accent-50 hover:text-accent-700">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="sticky top-0 z-20 flex flex-wrap items-center gap-4 rounded-lg border border-neutral-200 bg-white p-4 text-sm shadow-md">
            <span class="font-medium text-neutral-700">Legend:</span>
            <span class="inline-flex items-center gap-2">
                <span class="h-4 w-4 rounded border border-accent-700 bg-accent-600"></span>
                Blue Collar (BC)
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="h-4 w-4 rounded border border-neutral-300 bg-white"></span>
                White Collar Management (WCM)
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="h-4 w-4 rounded border border-pink-700 bg-pink-500"></span>
                Internship (Non BC/WCM)
            </span>
            @if ($filtered)
                <span class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
                    <span class="h-2 w-2 rounded-full bg-brand-500"></span>
                    Showing {{ $matchCount }} {{ Str::plural('match', $matchCount) }} and their reporting line to the top
                </span>
            @endif
            <span class="ml-auto inline-flex gap-2">
                <button type="button" @click="$dispatch('org-expand-all')" class="rounded-md border border-neutral-300 bg-white px-3 py-1 text-xs font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Expand all</button>
                <button type="button" @click="$dispatch('org-collapse-all')" class="rounded-md border border-neutral-300 bg-white px-3 py-1 text-xs font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Collapse all</button>
            </span>
        </div>

        @if ($roots->isEmpty())
            <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center text-sm text-neutral-500">
                @if ($filtered)
                    No employees match your search or filters.
                @else
                    No employees to show yet.
                @endif
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-md p-8 shadow-md">
                <ul class="org-chart-tree">
                    @foreach ($roots as $root)
                        @include('organization-chart._node', ['employee' => $root, 'childrenByParent' => $childrenByParent, 'highlightIds' => $highlightIds, 'forceExpand' => $filtered])
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
