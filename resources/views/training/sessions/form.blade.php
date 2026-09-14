@php
$isEdit = $session !== null;
$old = fn ($field, $default = null) => old($field, $session?->$field ?? $default);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit Session' : 'Add Session' }} — {{ $program->title }}</x-slot>

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6">
        <form method="POST" action="{{ $isEdit ? route('training.sessions.update', $session) : route('training.programs.sessions.store', $program) }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="session_title" value="Session Title (optional)" />
                    <x-text-input id="session_title" name="session_title" class="mt-1 block w-full" value="{{ $old('session_title') }}" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="start_date" value="Start Date" />
                        <x-text-input id="start_date" type="date" name="start_date" class="mt-1 block w-full" value="{{ $old('start_date') ? \Illuminate\Support\Carbon::parse($old('start_date'))->format('Y-m-d') : '' }}" required />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="End Date" />
                        <x-text-input id="end_date" type="date" name="end_date" class="mt-1 block w-full" value="{{ $old('end_date') ? \Illuminate\Support\Carbon::parse($old('end_date'))->format('Y-m-d') : '' }}" required />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="start_time" value="Start Time (optional)" />
                        <x-text-input id="start_time" type="time" name="start_time" class="mt-1 block w-full" value="{{ $old('start_time') }}" />
                    </div>
                    <div>
                        <x-input-label for="end_time" value="End Time (optional)" />
                        <x-text-input id="end_time" type="time" name="end_time" class="mt-1 block w-full" value="{{ $old('end_time') }}" />
                        <x-input-error :messages="$errors->get('end_time')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="location_id" value="Location" />
                    <select id="location_id" name="location_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected($old('location_id') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="trainer_name" value="Trainer Name (optional)" />
                    <x-text-input id="trainer_name" name="trainer_name" class="mt-1 block w-full" value="{{ $old('trainer_name') }}" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="max_participants" value="Max Participants (optional)" />
                        <x-text-input id="max_participants" type="number" min="1" name="max_participants" class="mt-1 block w-full" value="{{ $old('max_participants') }}" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($old('status', 'scheduled') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label for="notes" value="Notes (optional)" />
                    <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('notes') }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ $isEdit ? route('training.sessions.show', $session) : route('training.programs.show', $program) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
