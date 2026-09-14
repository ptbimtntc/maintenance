<x-app-layout>
    <x-slot name="header">Position Skill Requirements</x-slot>

    <div class="space-y-4">
        <a href="{{ route('skills.landing') }}" class="text-sm text-slate-600 hover:underline">&larr; Back to Skills &amp; Competencies</a>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Position</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Department</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Required Skills</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($positions as $position)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $position->title }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $position->department?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $position->skill_requirements_count }} skill(s)</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('skills.positions.edit', $position) }}" class="text-slate-600 hover:underline">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-500">No active positions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $positions->links() }}
    </div>
</x-app-layout>
