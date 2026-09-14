@php
$statusStyles = ['draft' => 'bg-gray-100 text-gray-700', 'active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-500'];
@endphp

<x-app-layout>
    <x-slot name="header">Training Programs</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">{{ $programs->total() }} program(s).</p>
            @can(\App\Enums\PermissionName::ManageTraining->value)
                <a href="{{ route('training.programs.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Add Program</a>
            @endcan
        </div>

        <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-4">
            <select name="training_category_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['training_category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-md border-gray-300 text-sm">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('training.programs.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Title</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Category</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Sessions</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($programs as $program)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $program->title }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $program->trainingCategory?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $program->trainingType?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $program->sessions_count }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$program->status] }}">{{ ucfirst($program->status) }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('training.programs.show', $program) }}" class="text-slate-600 hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No training programs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $programs->links() }}
    </div>
</x-app-layout>
