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

    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-neutral-200 bg-white p-4 text-sm">
            <span class="font-medium text-neutral-700">Legend:</span>
            <span class="inline-flex items-center gap-2">
                <span class="h-4 w-4 rounded border border-accent-700 bg-accent-600"></span>
                Blue Collar (BC)
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="h-4 w-4 rounded border border-neutral-300 bg-white"></span>
                White Collar Management (WCM)
            </span>
            <span class="ml-auto inline-flex gap-2">
                <button type="button" @click="$dispatch('org-expand-all')" class="rounded-md border border-neutral-300 bg-white px-3 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50">Expand all</button>
                <button type="button" @click="$dispatch('org-collapse-all')" class="rounded-md border border-neutral-300 bg-white px-3 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50">Collapse all</button>
            </span>
        </div>

        @if ($roots->isEmpty())
            <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center text-sm text-neutral-500">
                No employees to show yet.
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white p-8">
                <ul class="org-chart-tree">
                    @foreach ($roots as $root)
                        @include('organization-chart._node', ['employee' => $root, 'childrenByParent' => $childrenByParent])
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
