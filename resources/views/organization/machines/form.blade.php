@php
$isEdit = $record !== null;
$old = fn ($field, $default = null) => old($field, $record?->$field ?? $default);
$originQuery = array_filter(['from' => $from]);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit' : 'Add' }} {{ $config['singular'] }}</x-slot>

    <div class="max-w-2xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
        <form method="POST" action="{{ $isEdit ? route('organization.update', [$type, $record->id, ...$originQuery]) : route('organization.store', [$type, ...$originQuery]) }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="machine_no" value="Machine Number" />
                    <x-text-input id="machine_no" name="machine_no" class="mt-1 block w-full" value="{{ $old('machine_no') }}" placeholder="e.g. 50-TD101" required />
                    <x-input-error :messages="$errors->get('machine_no')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="func_location" value="Func. Location (optional)" />
                    <x-text-input id="func_location" name="func_location" class="mt-1 block w-full" value="{{ $old('func_location') }}" placeholder="e.g. ID01-01-CB-01-TD101" />
                    <x-input-error :messages="$errors->get('func_location')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="machine_type" value="Machine Type" />
                    <x-text-input id="machine_type" name="machine_type" list="available-machine-types" class="mt-1 block w-full" value="{{ $old('machine_type') }}" placeholder="e.g. Tubular Strander" required />
                    <datalist id="available-machine-types">
                        @foreach ($availableMachineTypes as $mType)
                            <option value="{{ $mType }}"></option>
                        @endforeach
                    </datalist>
                    <x-input-error :messages="$errors->get('machine_type')" class="mt-1" />
                    <p class="mt-1 text-xs text-neutral-500">You can type a new machine type or pick from existing ones.</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="secondary" href="{{ route('organization.index', [$type, ...$originQuery]) }}">Cancel</x-button>
                <x-button type="submit">Save</x-button>
            </div>
        </form>
    </div>
</x-app-layout>

