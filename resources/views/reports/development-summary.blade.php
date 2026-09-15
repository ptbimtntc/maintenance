<x-app-layout>
    <x-slot name="header">Employee Development Summary</x-slot>

    <div class="space-y-8">
        <a href="{{ route('reports.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Back to Reports</a>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-dashboard-stat label="Total Development Plans" :value="$totalPlans" />
            <x-dashboard-stat label="Overdue (past target date)" :value="$overdue" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">By Status</h2>
                <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($byStatus as $status => $count)
                                <tr><td class="px-4 py-3 text-gray-800">{{ ucwords(str_replace('_', ' ', $status)) }}</td><td class="px-4 py-3 text-right font-medium text-gray-900">{{ $count }}</td></tr>
                            @empty
                                <tr><td class="px-4 py-8 text-center text-gray-500">No plans yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">By Development Action</h2>
                <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($byAction as $action => $count)
                                <tr><td class="px-4 py-3 text-gray-800">{{ \App\Models\EmployeeDevelopmentPlan::ACTIONS[$action] ?? $action }}</td><td class="px-4 py-3 text-right font-medium text-gray-900">{{ $count }}</td></tr>
                            @empty
                                <tr><td class="px-4 py-8 text-center text-gray-500">No plans yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">By Priority</h2>
                <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($byPriority as $priority => $count)
                                <tr><td class="px-4 py-3 text-gray-800">{{ ucfirst($priority) }}</td><td class="px-4 py-3 text-right font-medium text-gray-900">{{ $count }}</td></tr>
                            @empty
                                <tr><td class="px-4 py-8 text-center text-gray-500">No plans yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
