@php
$isEdit = $plan !== null;
$old = fn ($field, $default = null) => old($field, $plan?->$field ?? $default);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit' : 'Add' }} Development Plan — {{ $employee->full_name }}</x-slot>

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6">
        <form method="POST" action="{{ $isEdit ? route('employees.development-plans.update', [$employee, $plan]) : route('employees.development-plans.store', $employee) }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="development_objective" value="Development Objective" />
                    <x-text-input id="development_objective" name="development_objective" class="mt-1 block w-full" value="{{ $old('development_objective') }}" required />
                    <x-input-error :messages="$errors->get('development_objective')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="related_skill_id" value="Related Skill (optional)" />
                    <select id="related_skill_id" name="related_skill_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        @foreach ($skills as $skill)
                            <option value="{{ $skill->id }}" @selected($old('related_skill_id') == $skill->id)>{{ $skill->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="current_competency_level_id" value="Current Competency (optional)" />
                        <select id="current_competency_level_id" name="current_competency_level_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($competencyLevels as $level)
                                <option value="{{ $level->id }}" @selected($old('current_competency_level_id') == $level->id)>{{ $level->level_number }} — {{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="target_competency_level_id" value="Target Competency (optional)" />
                        <select id="target_competency_level_id" name="target_competency_level_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($competencyLevels as $level)
                                <option value="{{ $level->id }}" @selected($old('target_competency_level_id') == $level->id)>{{ $level->level_number }} — {{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label for="development_action" value="Development Action" />
                    <select id="development_action" name="development_action" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($actions as $value => $label)
                            <option value="{{ $value }}" @selected($old('development_action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="recommended_training_program_id" value="Recommended Training Program (optional)" />
                    <select id="recommended_training_program_id" name="recommended_training_program_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        @foreach ($trainingPrograms as $program)
                            <option value="{{ $program->id }}" @selected($old('recommended_training_program_id') == $program->id)>{{ $program->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="mentor_employee_id" value="Mentor / Coach (optional)" />
                    <select id="mentor_employee_id" name="mentor_employee_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        @foreach ($possibleMentors as $mentor)
                            <option value="{{ $mentor->id }}" @selected($old('mentor_employee_id') == $mentor->id)>{{ $mentor->full_name }} ({{ $mentor->employee_number }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="priority" value="Priority" />
                        <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority }}" @selected($old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($old('status', 'not_started') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="progress_percentage" value="Progress (%)" />
                        <x-text-input id="progress_percentage" type="number" min="0" max="100" name="progress_percentage" class="mt-1 block w-full" value="{{ $old('progress_percentage', 0) }}" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="target_completion_date" value="Target Completion" />
                        <x-text-input id="target_completion_date" type="date" name="target_completion_date" class="mt-1 block w-full" value="{{ $old('target_completion_date') ? \Illuminate\Support\Carbon::parse($old('target_completion_date'))->format('Y-m-d') : '' }}" />
                    </div>
                    <div>
                        <x-input-label for="review_date" value="Review Date" />
                        <x-text-input id="review_date" type="date" name="review_date" class="mt-1 block w-full" value="{{ $old('review_date') ? \Illuminate\Support\Carbon::parse($old('review_date'))->format('Y-m-d') : '' }}" />
                    </div>
                    <div>
                        <x-input-label for="completion_date" value="Completion Date" />
                        <x-text-input id="completion_date" type="date" name="completion_date" class="mt-1 block w-full" value="{{ $old('completion_date') ? \Illuminate\Support\Carbon::parse($old('completion_date'))->format('Y-m-d') : '' }}" />
                    </div>
                </div>

                <div>
                    <x-input-label for="manager_remarks" value="Manager Remarks" />
                    <textarea id="manager_remarks" name="manager_remarks" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('manager_remarks') }}</textarea>
                </div>

                <div>
                    <x-input-label for="employee_remarks" value="Employee Remarks" />
                    <textarea id="employee_remarks" name="employee_remarks" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('employee_remarks') }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('employees.show', $employee) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
