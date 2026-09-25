<x-app-layout>
    <x-slot name="header">Position Skill Requirements</x-slot>

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('skills.landing') }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to Skills &amp; Competencies</a>

            <div class="flex items-center gap-2">
                <a href="{{ route('skills.positions.export') }}" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                Export XLSX
            </a>

                @if (auth()->user()->canEditMenu(\App\Enums\MenuKey::SkillsCompetencies))
                    <form method="POST" action="{{ route('skills.positions.import') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-4.5L12 16.5m0 0 4.5-4.5M12 16.5V3" /></svg>
                            Import XLSX
                            <input type="file" name="file" accept=".xlsx" class="hidden" onchange="this.form.requestSubmit()">
                        </label>
                    </form>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        @if (session('import_errors'))
            <div class="rounded-md border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">{{ session('import_errors') }}</div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Position</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Department</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Required Skills</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($positions as $position)
                        <tr>
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $position->title }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $position->department?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $position->skill_requirements_count }} skill(s)</td>
                            <td class="px-3 py-2 text-right">
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
