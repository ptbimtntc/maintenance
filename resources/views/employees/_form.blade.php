@php
$employee = $employee ?? null;
$old = fn ($field, $default = null) => old($field, $employee?->$field ?? $default);
@endphp

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="employee_number" value="Employee Number" />
        <x-text-input id="employee_number" name="employee_number" class="mt-1 block w-full" value="{{ $old('employee_number') }}" required />
        <x-input-error :messages="$errors->get('employee_number')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="full_name" value="Full Name" />
        <x-text-input id="full_name" name="full_name" class="mt-1 block w-full" value="{{ $old('full_name') }}" required />
        <x-input-error :messages="$errors->get('full_name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="preferred_name" value="Preferred Name" />
        <x-text-input id="preferred_name" name="preferred_name" class="mt-1 block w-full" value="{{ $old('preferred_name') }}" />
        <x-input-error :messages="$errors->get('preferred_name')" class="mt-1" />
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
        <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">Not specified</option>
            <option value="Male" @selected($old('gender') === 'Male')>Male</option>
            <option value="Female" @selected($old('gender') === 'Female')>Female</option>
        </select>
        <x-input-error :messages="$errors->get('gender')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="date_of_birth" value="Date of Birth (optional)" />
        <x-text-input id="date_of_birth" type="date" name="date_of_birth" class="mt-1 block w-full" value="{{ $old('date_of_birth') ? \Illuminate\Support\Carbon::parse($old('date_of_birth'))->format('Y-m-d') : '' }}" />
        <x-input-error :messages="$errors->get('date_of_birth')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="date_joined" value="Date Joined" />
        <x-text-input id="date_joined" type="date" name="date_joined" class="mt-1 block w-full" value="{{ $old('date_joined') ? \Illuminate\Support\Carbon::parse($old('date_joined'))->format('Y-m-d') : '' }}" />
        <x-input-error :messages="$errors->get('date_joined')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="department_id" value="Department" />
        <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected($old('department_id') == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('department_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="division_id" value="Division" />
        <select id="division_id" name="division_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($divisions as $division)
                <option value="{{ $division->id }}" @selected($old('division_id') == $division->id)>{{ $division->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('division_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="maintenance_area_id" value="Maintenance Area" />
        <select id="maintenance_area_id" name="maintenance_area_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($maintenanceAreas as $area)
                <option value="{{ $area->id }}" @selected($old('maintenance_area_id') == $area->id)>{{ $area->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('maintenance_area_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="maintenance_team_id" value="Maintenance Team" />
        <select id="maintenance_team_id" name="maintenance_team_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($maintenanceTeams as $team)
                <option value="{{ $team->id }}" @selected($old('maintenance_team_id') == $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('maintenance_team_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="position_id" value="Position" />
        <select id="position_id" name="position_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($positions as $position)
                <option value="{{ $position->id }}" @selected($old('position_id') == $position->id)>{{ $position->title }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('position_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="employment_type_id" value="Employment Type" />
        <select id="employment_type_id" name="employment_type_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($employmentTypes as $type)
                <option value="{{ $type->id }}" @selected($old('employment_type_id') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('employment_type_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="employment_status_id" value="Employment Status" />
        <select id="employment_status_id" name="employment_status_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($employmentStatuses as $status)
                <option value="{{ $status->id }}" @selected($old('employment_status_id') == $status->id)>{{ $status->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('employment_status_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="location_id" value="Work Location" />
        <select id="location_id" name="location_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}" @selected($old('location_id') == $location->id)>{{ $location->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('location_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="shift_id" value="Shift" />
        <select id="shift_id" name="shift_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($shifts as $shift)
                <option value="{{ $shift->id }}" @selected($old('shift_id') == $shift->id)>{{ $shift->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('shift_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="supervisor_id" value="Supervisor" />
        <select id="supervisor_id" name="supervisor_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($possibleSupervisors as $person)
                <option value="{{ $person->id }}" @selected($old('supervisor_id') == $person->id)>{{ $person->full_name }} ({{ $person->employee_number }})</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('supervisor_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="manager_id" value="Manager" />
        <select id="manager_id" name="manager_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($possibleSupervisors as $person)
                <option value="{{ $person->id }}" @selected($old('manager_id') == $person->id)>{{ $person->full_name }} ({{ $person->employee_number }})</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('manager_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="education" value="Education" />
        <x-text-input id="education" name="education" class="mt-1 block w-full" value="{{ $old('education') }}" />
        <x-input-error :messages="$errors->get('education')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="technical_background" value="Technical Background" />
        <x-text-input id="technical_background" name="technical_background" class="mt-1 block w-full" value="{{ $old('technical_background') }}" />
        <x-input-error :messages="$errors->get('technical_background')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="years_of_experience" value="Years of Experience" />
        <x-text-input id="years_of_experience" type="number" min="0" max="60" name="years_of_experience" class="mt-1 block w-full" value="{{ $old('years_of_experience') }}" />
        <x-input-error :messages="$errors->get('years_of_experience')" class="mt-1" />
    </div>
</div>

<div class="mt-6">
    <x-input-label for="notes" value="Notes / Remarks" />
    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('notes') }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
</div>
