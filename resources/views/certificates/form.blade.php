@php
$isEdit = $certificate !== null;
$old = fn ($field, $default = null) => old($field, $certificate?->$field ?? $default);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit' : 'Add' }} Certificate — {{ $employee->full_name }}</x-slot>

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6">
        <form method="POST" action="{{ $isEdit ? route('employees.certificates.update', [$employee, $certificate]) : route('employees.certificates.store', $employee) }}" enctype="multipart/form-data">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="Certificate Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ $old('name') }}" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="certificate_type_id" value="Certificate Type" />
                        <select id="certificate_type_id" name="certificate_type_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($certificateTypes as $type)
                                <option value="{{ $type->id }}" @selected($old('certificate_type_id') == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="certificate_number" value="Certificate Number" />
                        <x-text-input id="certificate_number" name="certificate_number" class="mt-1 block w-full" value="{{ $old('certificate_number') }}" />
                    </div>
                </div>

                <div>
                    <x-input-label for="issuing_organization" value="Issuing Organization" />
                    <x-text-input id="issuing_organization" name="issuing_organization" class="mt-1 block w-full" value="{{ $old('issuing_organization') }}" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="issue_date" value="Issue Date" />
                        <x-text-input id="issue_date" type="date" name="issue_date" class="mt-1 block w-full" value="{{ $old('issue_date') ? \Illuminate\Support\Carbon::parse($old('issue_date'))->format('Y-m-d') : '' }}" />
                    </div>
                    <div>
                        <x-input-label for="expiry_date" value="Expiry Date (leave blank if no expiry)" />
                        <x-text-input id="expiry_date" type="date" name="expiry_date" class="mt-1 block w-full" value="{{ $old('expiry_date') ? \Illuminate\Support\Carbon::parse($old('expiry_date'))->format('Y-m-d') : '' }}" />
                        <x-input-error :messages="$errors->get('expiry_date')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="related_skill_id" value="Related Skill (optional)" />
                        <select id="related_skill_id" name="related_skill_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($skills as $skill)
                                <option value="{{ $skill->id }}" @selected($old('related_skill_id') == $skill->id)>{{ $skill->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="related_training_program_id" value="Related Training (optional)" />
                        <select id="related_training_program_id" name="related_training_program_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($trainingPrograms as $program)
                                <option value="{{ $program->id }}" @selected($old('related_training_program_id') == $program->id)>{{ $program->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label for="verification_status" value="Verification Status" />
                    <select id="verification_status" name="verification_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($verificationStatuses as $status)
                            <option value="{{ $status }}" @selected($old('verification_status', 'verified') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">A certificate is never assumed valid just because a file was uploaded — mark it verified once you've checked it.</p>
                </div>

                <div>
                    <x-input-label for="file" value="Certificate File (optional, PDF/JPG/PNG, max 5MB)" />
                    <input id="file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                    <x-input-error :messages="$errors->get('file')" class="mt-1" />
                    @if ($isEdit && $certificate->file_path)
                        <p class="mt-1 text-xs text-gray-500">Current file: {{ $certificate->file_original_name }} — uploading a new one will replace it.</p>
                    @endif
                </div>

                <div>
                    <x-input-label for="verification_notes" value="Verification Notes (optional)" />
                    <textarea id="verification_notes" name="verification_notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('verification_notes') }}</textarea>
                </div>

                <div>
                    <x-input-label for="remarks" value="Remarks (optional)" />
                    <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('remarks') }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('employees.show', $employee) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save Certificate</button>
            </div>
        </form>
    </div>
</x-app-layout>
