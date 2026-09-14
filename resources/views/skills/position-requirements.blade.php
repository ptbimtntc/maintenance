<x-app-layout>
    <x-slot name="header">Skill Requirements — {{ $position->title }}</x-slot>

    <div class="space-y-6">
        <a href="{{ route('skills.positions.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Back to Positions</a>

        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Skill</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Required Level</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Notes</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($position->skillRequirements as $requirement)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $requirement->skill->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" style="background-color: {{ $requirement->requiredCompetencyLevel->color ?? '#e5e7eb' }}22; color: {{ $requirement->requiredCompetencyLevel->color ?? '#374151' }}">
                                    {{ $requirement->requiredCompetencyLevel->level_number }} — {{ $requirement->requiredCompetencyLevel->name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $requirement->notes ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('skills.positions.requirements.destroy', [$position, $requirement]) }}" onsubmit="return confirm('Remove this requirement?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-500">No skill requirements defined yet for this position.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="max-w-lg rounded-lg border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900">Add a Skill Requirement</h3>

            <form method="POST" action="{{ route('skills.positions.requirements.store', $position) }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <x-input-label for="skill_id" value="Skill" />
                    <select id="skill_id" name="skill_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($skills as $skill)
                            <option value="{{ $skill->id }}" @selected(old('skill_id') == $skill->id)>{{ $skill->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('skill_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="required_competency_level_id" value="Required Competency Level" />
                    <select id="required_competency_level_id" name="required_competency_level_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($competencyLevels as $level)
                            <option value="{{ $level->id }}" @selected(old('required_competency_level_id') == $level->id)>{{ $level->level_number }} — {{ $level->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('required_competency_level_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="notes" value="Notes (optional)" />
                    <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                </div>

                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Add Requirement</button>
            </form>
        </div>
    </div>
</x-app-layout>
