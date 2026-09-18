@php
$statusStyles = ['draft' => 'bg-neutral-100 text-neutral-700', 'active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-neutral-100 text-neutral-500'];
@endphp

<x-app-layout>
    <x-slot name="header">Training Programs</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <p class="text-sm text-neutral-500">{{ $programs->total() }} program(s).</p>
                <x-read-only-badge menu="training" />
            </div>
            @can(\App\Enums\PermissionName::ManageTraining->value)
                <a href="{{ route('training.programs.create') }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add Program</a>
            @endcan
        </div>

        @php
            $activeFieldClass = 'border-brand-400 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200';
            $defaultFieldClass = 'border-neutral-300';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-neutral-200 bg-white p-4 sm:grid-cols-4">
                <select name="training_category_id" class="rounded-md text-sm {{ filled($filters['training_category_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(($filters['training_category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-md text-sm {{ filled($filters['status'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Filter</button>
                    <a href="{{ route('training.programs.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Title</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Category</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Sessions</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($programs as $program)
                        <tr>
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $program->title }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $program->trainingCategory?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $program->trainingType?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $program->sessions_count }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$program->status] }}">{{ ucfirst($program->status) }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('training.programs.show', $program) }}" class="text-neutral-600 hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-neutral-500">No training programs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $programs->links() }}
    </div>
</x-app-layout>
