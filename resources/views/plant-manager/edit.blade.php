<x-app-layout>
    <x-slot name="header">Plant Manager</x-slot>

    @if ($employee)
        @php($old = fn ($field) => old($field, $employee->$field))

        <div class="max-w-4xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
            <p class="mb-6 text-sm text-neutral-500">
                The Plant Manager is the company's highest leader. They are kept out of the Employees list and every employee total, and are managed only here.
            </p>

            <form method="POST" action="{{ route('plant-manager.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="employee_number" value="Employee Number" />
                        <x-text-input id="employee_number" name="employee_number" class="mt-1 block w-full" value="{{ $old('employee_number') }}" required />
                        <x-input-error :messages="$errors->get('employee_number')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="sap_id" value="ID SAP" />
                        <x-text-input id="sap_id" name="sap_id" class="mt-1 block w-full" value="{{ $old('sap_id') }}" />
                        <x-input-error :messages="$errors->get('sap_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="full_name" value="Full Name" />
                        <x-text-input id="full_name" name="full_name" class="mt-1 block w-full" value="{{ $old('full_name') }}" required />
                        <x-input-error :messages="$errors->get('full_name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="photo" value="Photo (optional)" />
                        @include('employees._photo-field', ['employee' => $employee])
                        <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" type="email" name="email" class="mt-1 block w-full" value="{{ $old('email') }}" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" class="mt-1 block w-full" value="{{ $old('phone') }}" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="gender" value="Gender (optional)" />
                        <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                            <option value="">—</option>
                            @foreach (['Male', 'Female'] as $gender)
                                <option value="{{ $gender }}" @selected($old('gender') === $gender)>{{ $gender }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('gender')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="date_of_birth" value="Date of Birth (optional)" />
                        <x-text-input id="date_of_birth" type="date" name="date_of_birth" class="mt-1 block w-full" value="{{ old('date_of_birth', $employee->date_of_birth?->format('Y-m-d')) }}" />
                        <x-input-error :messages="$errors->get('date_of_birth')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="date_joined" value="Date Joined" />
                        <x-text-input id="date_joined" type="date" name="date_joined" class="mt-1 block w-full" value="{{ old('date_joined', $employee->date_joined?->format('Y-m-d')) }}" />
                        <x-input-error :messages="$errors->get('date_joined')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">{{ $old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-button type="submit">Save Changes</x-button>
                </div>
            </form>
        </div>
    @else
        <div class="max-w-4xl rounded-lg border border-neutral-200 bg-white p-6 text-sm text-neutral-500 shadow-md">
            No Plant Manager has been set up yet.
        </div>
    @endif
</x-app-layout>
