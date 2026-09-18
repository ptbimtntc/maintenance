<x-app-layout>
    <x-slot name="header">Position Skill Requirements</x-slot>

    <div class="space-y-4">
        <a href="{{ route('skills.landing') }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to Skills &amp; Competencies</a>

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Position</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Department</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Required Skills</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($positions as $position)
                        <tr>
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $position->title }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $position->department?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $position->skill_requirements_count }} skill(s)</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('skills.positions.edit', $position) }}" class="text-neutral-600 hover:underline">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-neutral-500">No active positions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $positions->links() }}
    </div>
</x-app-layout>
