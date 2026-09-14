@php
$isEdit = $jobDescription !== null;
$old = fn ($field, $default = null) => old($field, $jobDescription?->$field ?? $default);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit Job Description (v'.$jobDescription->version.')' : 'Add Job Description' }}</x-slot>

    <div class="max-w-3xl rounded-lg border border-gray-200 bg-white p-6">
        <form method="POST" action="{{ $isEdit ? route('job-descriptions.update', $jobDescription) : route('job-descriptions.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="position_id" value="Position" />
                        <select id="position_id" name="position_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" {{ $isEdit ? 'disabled' : '' }}>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}" @selected($old('position_id') == $position->id)>{{ $position->title }}</option>
                            @endforeach
                        </select>
                        @if ($isEdit)
                            <input type="hidden" name="position_id" value="{{ $jobDescription->position_id }}" />
                            <p class="mt-1 text-xs text-gray-400">Position cannot change once a job description exists. Create a new revision to move it.</p>
                        @endif
                        <x-input-error :messages="$errors->get('position_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="reports_to_position_id" value="Reports To (optional)" />
                        <select id="reports_to_position_id" name="reports_to_position_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}" @selected($old('reports_to_position_id') == $position->id)>{{ $position->title }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('reports_to_position_id')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="job_title" value="Job Title" />
                    <x-text-input id="job_title" name="job_title" class="mt-1 block w-full" value="{{ $old('job_title') }}" required />
                    <x-input-error :messages="$errors->get('job_title')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="job_purpose" value="Job Purpose" />
                    <textarea id="job_purpose" name="job_purpose" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('job_purpose') }}</textarea>
                    <x-input-error :messages="$errors->get('job_purpose')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="main_responsibilities" value="Main Responsibilities" />
                    <textarea id="main_responsibilities" name="main_responsibilities" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('main_responsibilities') }}</textarea>
                    <x-input-error :messages="$errors->get('main_responsibilities')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="detailed_duties" value="Detailed Duties" />
                    <textarea id="detailed_duties" name="detailed_duties" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('detailed_duties') }}</textarea>
                    <x-input-error :messages="$errors->get('detailed_duties')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="required_education" value="Required Education" />
                        <x-text-input id="required_education" name="required_education" class="mt-1 block w-full" value="{{ $old('required_education') }}" />
                        <x-input-error :messages="$errors->get('required_education')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="required_experience" value="Required Experience" />
                        <x-text-input id="required_experience" name="required_experience" class="mt-1 block w-full" value="{{ $old('required_experience') }}" />
                        <x-input-error :messages="$errors->get('required_experience')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="required_technical_skills" value="Required Technical Skills" />
                    <textarea id="required_technical_skills" name="required_technical_skills" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('required_technical_skills') }}</textarea>
                    <x-input-error :messages="$errors->get('required_technical_skills')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="required_soft_skills" value="Required Soft Skills" />
                    <textarea id="required_soft_skills" name="required_soft_skills" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('required_soft_skills') }}</textarea>
                    <x-input-error :messages="$errors->get('required_soft_skills')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="required_certifications" value="Required Certifications" />
                    <textarea id="required_certifications" name="required_certifications" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('required_certifications') }}</textarea>
                    <x-input-error :messages="$errors->get('required_certifications')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="safety_responsibilities" value="Safety Responsibilities" />
                    <textarea id="safety_responsibilities" name="safety_responsibilities" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('safety_responsibilities') }}</textarea>
                    <x-input-error :messages="$errors->get('safety_responsibilities')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="direct_reports_summary" value="Direct Reports (summary)" />
                    <x-text-input id="direct_reports_summary" name="direct_reports_summary" class="mt-1 block w-full" value="{{ $old('direct_reports_summary') }}" placeholder="e.g. 3 Maintenance Technicians" />
                    <x-input-error :messages="$errors->get('direct_reports_summary')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="effective_date" value="Effective Date" />
                        <x-text-input id="effective_date" type="date" name="effective_date" class="mt-1 block w-full" value="{{ $old('effective_date') ? \Illuminate\Support\Carbon::parse($old('effective_date'))->format('Y-m-d') : '' }}" />
                        <x-input-error :messages="$errors->get('effective_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="review_date" value="Review Date" />
                        <x-text-input id="review_date" type="date" name="review_date" class="mt-1 block w-full" value="{{ $old('review_date') ? \Illuminate\Support\Carbon::parse($old('review_date'))->format('Y-m-d') : '' }}" />
                        <x-input-error :messages="$errors->get('review_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach (\App\Models\JobDescription::STATUSES as $status)
                                <option value="{{ $status }}" @selected($old('status', 'draft') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="remarks" value="Remarks (optional)" />
                    <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('remarks') }}</textarea>
                    <x-input-error :messages="$errors->get('remarks')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ $isEdit ? route('job-descriptions.show', $jobDescription) : route('job-descriptions.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
