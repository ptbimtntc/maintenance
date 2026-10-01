@php
$isEdit = $comm !== null;
$value = fn ($field) => old($field, $isEdit ? ($field === 'comm_date' ? $comm->comm_date->toDateString() : $comm->{$field}) : ($defaults[$field] ?? null));
$input = 'mt-1 block w-full rounded-md border-neutral-300 text-sm';
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit Shift Comm '.$comm->comm_number : 'New Shift Comm' }}</x-slot>

    <div class="max-w-3xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
        <form method="POST" action="{{ $isEdit ? route('shift-comm.update', $comm) : route('shift-comm.store') }}" class="space-y-4">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div>
                    <x-input-label for="comm_number" value="ID" />
                    <input id="comm_number" type="text" value="{{ $isEdit ? $comm->comm_number : 'Assigned on save' }}" disabled class="{{ $input }} bg-neutral-50 text-neutral-500">
                </div>
                <div>
                    <x-input-label for="comm_date" value="Date" />
                    <input id="comm_date" type="date" name="comm_date" value="{{ $value('comm_date') }}" @required(! $isEdit) @disabled($isEdit) class="{{ $input }} @if ($isEdit) bg-neutral-50 text-neutral-500 @endif">
                    <x-input-error :messages="$errors->get('comm_date')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="shift" value="Shift" />
                    <select id="shift" name="shift" @disabled($isEdit) class="{{ $input }} @if ($isEdit) bg-neutral-50 text-neutral-500 @endif">
                        @foreach (\App\Models\ShiftComm::SHIFTS as $v => $label)
                            <option value="{{ $v }}" @selected($value('shift') == $v)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('shift')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="team" value="Team" />
                    <select id="team" name="team" required class="{{ $input }}">
                        <option value="">Select team</option>
                        @foreach (\App\Models\ShiftComm::TEAMS as $team)
                            <option value="{{ $team }}" @selected($value('team') === $team)>{{ $team }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('team')" class="mt-1" />
                </div>
            </div>
            @if ($isEdit)
                <p class="text-[11px] text-neutral-400">Date and shift are part of the ID, so they can't be changed after creation.</p>
            @endif

            <div>
                <x-input-label for="machine_no" value="Machine No." />
                <input id="machine_no" type="text" name="machine_no" maxlength="50" required value="{{ $value('machine_no') }}" class="{{ $input }} sm:max-w-xs">
                <x-input-error :messages="$errors->get('machine_no')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="problem" value="Problem" />
                <textarea id="problem" name="problem" rows="4" required maxlength="5000" class="{{ $input }}">{{ $value('problem') }}</textarea>
                <x-input-error :messages="$errors->get('problem')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="progress" value="Progress" />
                <textarea id="progress" name="progress" rows="4" maxlength="5000" class="{{ $input }}">{{ $value('progress') }}</textarea>
                <x-input-error :messages="$errors->get('progress')" class="mt-1" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input-label for="construction" value="Construction" />
                    <input id="construction" type="text" name="construction" maxlength="150" list="construction-suggestions" autocomplete="off" value="{{ $value('construction') }}" class="{{ $input }}">
                    <datalist id="construction-suggestions">
                        @foreach ($constructions as $construction)
                            <option value="{{ $construction }}"></option>
                        @endforeach
                    </datalist>
                    <x-input-error :messages="$errors->get('construction')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="length_m" value="Length (m)" />
                    <input id="length_m" type="number" step="1" min="0" name="length_m" value="{{ $value('length_m') }}" class="{{ $input }}">
                    <x-input-error :messages="$errors->get('length_m')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label for="remark" value="Remark" />
                <textarea id="remark" name="remark" rows="3" maxlength="5000" class="{{ $input }}">{{ $value('remark') }}</textarea>
                <x-input-error :messages="$errors->get('remark')" class="mt-1" />
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create' }}</x-primary-button>
                <a href="{{ $isEdit ? route('shift-comm.show', $comm) : route('shift-comm.index') }}" class="text-sm text-neutral-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
