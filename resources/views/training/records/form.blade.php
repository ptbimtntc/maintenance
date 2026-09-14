<x-app-layout>
    <x-slot name="header">Add Training Record — {{ $employee->full_name }}</x-slot>

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6">
        <form method="POST" action="{{ route('employees.training-records.store', $employee) }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <x-input-label for="training_program_id" value="Training Program (optional)" />
                    <select id="training_program_id" name="training_program_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">— Ad-hoc / not linked to a program —</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}" @selected(old('training_program_id') == $program->id)>{{ $program->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="training_date" value="Training Date" />
                        <x-text-input id="training_date" type="date" name="training_date" class="mt-1 block w-full" value="{{ old('training_date', now()->format('Y-m-d')) }}" required />
                        <x-input-error :messages="$errors->get('training_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="duration_hours" value="Duration (hours)" />
                        <x-text-input id="duration_hours" type="number" step="0.5" name="duration_hours" class="mt-1 block w-full" value="{{ old('duration_hours') }}" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="training_type_id" value="Training Type" />
                        <select id="training_type_id" name="training_type_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" @selected(old('training_type_id') == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="training_provider_id" value="Provider (if external)" />
                        <select id="training_provider_id" name="training_provider_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}" @selected(old('training_provider_id') == $provider->id)>{{ $provider->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label for="trainer_name" value="Trainer Name (optional)" />
                    <x-text-input id="trainer_name" name="trainer_name" class="mt-1 block w-full" value="{{ old('trainer_name') }}" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="attendance_status" value="Attendance" />
                        <select id="attendance_status" name="attendance_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="attended" selected>Attended</option>
                            <option value="absent">Absent</option>
                            <option value="excused">Excused</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="completion_status" value="Completion" />
                        <select id="completion_status" name="completion_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="completed" selected>Completed</option>
                            <option value="incomplete">Incomplete</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="assessment_score" value="Assessment Score (optional)" />
                        <x-text-input id="assessment_score" type="number" step="0.01" min="0" max="100" name="assessment_score" class="mt-1 block w-full" value="{{ old('assessment_score') }}" />
                    </div>
                    <div>
                        <x-input-label for="assessment_result" value="Assessment Result (optional)" />
                        <select id="assessment_result" name="assessment_result" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            <option value="pass">Pass</option>
                            <option value="fail">Fail</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="competency_before_level_id" value="Competency Before (optional)" />
                        <select id="competency_before_level_id" name="competency_before_level_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($competencyLevels as $level)
                                <option value="{{ $level->id }}">{{ $level->level_number }} — {{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="competency_after_level_id" value="Competency After (optional)" />
                        <select id="competency_after_level_id" name="competency_after_level_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($competencyLevels as $level)
                                <option value="{{ $level->id }}">{{ $level->level_number }} — {{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="certificate_issued" value="1" class="rounded border-gray-300" />
                    <span class="text-sm text-gray-700">Certificate Issued</span>
                </label>

                <div>
                    <x-input-label for="certificate_reference" value="Certificate Reference (optional)" />
                    <x-text-input id="certificate_reference" name="certificate_reference" class="mt-1 block w-full" value="{{ old('certificate_reference') }}" />
                </div>

                <div>
                    <x-input-label for="remarks" value="Remarks (optional)" />
                    <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('remarks') }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('employees.show', $employee) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save Record</button>
            </div>
        </form>
    </div>
</x-app-layout>
