@php
$isEdit = $program !== null;
$old = fn ($field, $default = null) => old($field, $program?->$field ?? $default);
$selectedSkills = old('skills', $program?->skills->pluck('id')->toArray() ?? []);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit Training Program' : 'Add Training Program' }}</x-slot>

    <div class="max-w-3xl rounded-lg border border-neutral-200 bg-white p-6">
        <form method="POST" action="{{ $isEdit ? route('training.programs.update', $program) : route('training.programs.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ $old('title') }}" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="code" value="Code (optional)" />
                        <x-text-input id="code" name="code" class="mt-1 block w-full" value="{{ $old('code') }}" />
                        <x-input-error :messages="$errors->get('code')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($old('status', 'draft') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="training_category_id" value="Category" />
                        <select id="training_category_id" name="training_category_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                            <option value="">—</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($old('training_category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="training_type_id" value="Type" />
                        <select id="training_type_id" name="training_type_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                            <option value="">—</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" @selected($old('training_type_id') == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">{{ $old('description') }}</textarea>
                </div>

                <div>
                    <x-input-label for="objectives" value="Objectives" />
                    <textarea id="objectives" name="objectives" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">{{ $old('objectives') }}</textarea>
                </div>

                <div>
                    <x-input-label value="Related Skills" />
                    <select name="skills[]" multiple class="mt-1 block w-full rounded-md border-neutral-300 text-sm" size="5">
                        @foreach ($skills as $skill)
                            <option value="{{ $skill->id }}" @selected(in_array($skill->id, $selectedSkills))>{{ $skill->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-neutral-400">Hold Ctrl/Cmd to select multiple skills this program develops.</p>
                </div>

                <div>
                    <x-input-label for="target_audience" value="Target Audience" />
                    <x-text-input id="target_audience" name="target_audience" class="mt-1 block w-full" value="{{ $old('target_audience') }}" />
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_internal" value="1" @checked($old('is_internal', true)) class="rounded border-neutral-300" />
                    <span class="text-sm text-neutral-700">Internal Training</span>
                </label>

                <div class="rounded-md border border-brand-200 bg-brand-50/40 p-4">
                    <p class="mb-3 text-sm font-semibold text-neutral-800">Certification (used by the quiz &amp; certificate)</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="validity_months" value="Certificate Validity (months, blank = no expiry)" />
                            <x-text-input id="validity_months" type="number" min="1" max="120" name="validity_months" class="mt-1 block w-full" value="{{ $old('validity_months') }}" />
                        </div>
                        <div>
                            <x-input-label for="passing_score" value="Passing Score (0-100)" />
                            <x-text-input id="passing_score" type="number" min="0" max="100" name="passing_score" class="mt-1 block w-full" value="{{ $old('passing_score') }}" />
                        </div>
                        <div>
                            <x-input-label for="authorizer_name" value="Authorizer Name" />
                            <x-text-input id="authorizer_name" name="authorizer_name" class="mt-1 block w-full" value="{{ $old('authorizer_name') }}" />
                        </div>
                        <div>
                            <x-input-label for="authorizer_title" value="Authorizer Title" />
                            <x-text-input id="authorizer_title" name="authorizer_title" class="mt-1 block w-full" value="{{ $old('authorizer_title') }}" placeholder="Maintenance Manager" />
                        </div>
                        <div>
                            <x-input-label for="authorizer_signatory_id" value="Or pick a saved Signatory" />
                            <select id="authorizer_signatory_id" name="authorizer_signatory_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                                <option value="">—</option>
                                @foreach ($signatories as $signatory)
                                    <option value="{{ $signatory->id }}" @selected($old('authorizer_signatory_id') == $signatory->id)>{{ $signatory->name }}{{ $signatory->title ? " ({$signatory->title})" : '' }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-neutral-400">Fills in the name/title/signature above from <a href="{{ route('signatories.index') }}" class="underline" target="_blank">Signatories</a> - a manual upload below still wins.</p>
                        </div>
                        <div>
                            <x-input-label for="authorizer_signature" value="Authorizer Signature Image" />
                            @if ($program?->authorizer_signature_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($program->authorizer_signature_path) }}" alt="Authorizer signature" class="mt-1 h-14 border border-neutral-200 bg-white p-1">
                            @endif
                            <input id="authorizer_signature" type="file" name="authorizer_signature" accept="image/png,image/jpeg" class="mt-1 block w-full text-sm text-neutral-600 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm" />
                            <x-input-error :messages="$errors->get('authorizer_signature')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="trainer_signatory_id" value="Or pick a saved Signatory" />
                            <select id="trainer_signatory_id" name="trainer_signatory_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                                <option value="">—</option>
                                @foreach ($signatories as $signatory)
                                    <option value="{{ $signatory->id }}" @selected($old('trainer_signatory_id') == $signatory->id)>{{ $signatory->name }}{{ $signatory->title ? " ({$signatory->title})" : '' }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-neutral-400">Fills in the trainer name/signature below from <a href="{{ route('signatories.index') }}" class="underline" target="_blank">Signatories</a> - a manual upload below still wins.</p>
                        </div>
                        <div>
                            <x-input-label for="trainer_signature" value="Trainer Signature Image" />
                            @if ($program?->trainer_signature_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($program->trainer_signature_path) }}" alt="Trainer signature" class="mt-1 h-14 border border-neutral-200 bg-white p-1">
                            @endif
                            <input id="trainer_signature" type="file" name="trainer_signature" accept="image/png,image/jpeg" class="mt-1 block w-full text-sm text-neutral-600 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm" />
                            <p class="mt-1 text-xs text-neutral-400">PNG with a transparent background works best. Used on the printed certificate.</p>
                            <x-input-error :messages="$errors->get('trainer_signature')" class="mt-1" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="trainer_name" value="Trainer Name" />
                        <x-text-input id="trainer_name" name="trainer_name" class="mt-1 block w-full" value="{{ $old('trainer_name') }}" />
                    </div>
                    <div>
                        <x-input-label for="training_provider_id" value="Training Provider (if external)" />
                        <select id="training_provider_id" name="training_provider_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                            <option value="">—</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}" @selected($old('training_provider_id') == $provider->id)>{{ $provider->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label for="location_id" value="Location" />
                    <select id="location_id" name="location_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                        <option value="">—</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected($old('location_id') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <x-input-label for="duration_value" value="Duration" />
                        <x-text-input id="duration_value" type="number" step="0.5" name="duration_value" class="mt-1 block w-full" value="{{ $old('duration_value') }}" />
                    </div>
                    <div>
                        <x-input-label for="duration_unit" value="Unit" />
                        <select id="duration_unit" name="duration_unit" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                            <option value="">—</option>
                            <option value="hours" @selected($old('duration_unit') === 'hours')>Hours</option>
                            <option value="days" @selected($old('duration_unit') === 'days')>Days</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="estimated_cost" value="Estimated Cost" />
                        <x-text-input id="estimated_cost" type="number" step="0.01" name="estimated_cost" class="mt-1 block w-full" value="{{ $old('estimated_cost') }}" />
                    </div>
                    <div>
                        <x-input-label for="budget_reference" value="Budget Ref." />
                        <x-text-input id="budget_reference" name="budget_reference" class="mt-1 block w-full" value="{{ $old('budget_reference') }}" />
                    </div>
                </div>

                <div>
                    <x-input-label for="remarks" value="Remarks" />
                    <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">{{ $old('remarks') }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ $isEdit ? route('training.programs.show', $program) : route('training.programs.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</a>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
