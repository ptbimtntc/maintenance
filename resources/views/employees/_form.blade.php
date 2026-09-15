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
        <x-input-label for="photo" value="Photo (optional)" />
        @if ($employee?->photo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}" alt="Current photo" class="mt-1 mb-2 h-16 w-16 rounded-full object-cover">
        @endif
        <input id="photo" type="file" name="photo" accept="image/*" class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800" />
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
        <x-input-label for="business_unit_id" value="Business Unit" />
        <select id="business_unit_id" name="business_unit_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($businessUnits as $businessUnit)
                <option value="{{ $businessUnit->id }}" @selected($old('business_unit_id') == $businessUnit->id)>{{ $businessUnit->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('business_unit_id')" class="mt-1" />
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
        <x-input-label for="skill_position_id" value="Skill Position" />
        <select id="skill_position_id" name="skill_position_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($skillPositions as $skillPosition)
                <option value="{{ $skillPosition->id }}" @selected($old('skill_position_id') == $skillPosition->id)>{{ $skillPosition->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('skill_position_id')" class="mt-1" />
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
        <x-input-label for="employment_source_id" value="Employment Source" />
        <select id="employment_source_id" name="employment_source_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($employmentSources as $employmentSource)
                <option value="{{ $employmentSource->id }}" @selected($old('employment_source_id') == $employmentSource->id)>{{ $employmentSource->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('employment_source_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="workforce_category" value="Management" />
        <select id="workforce_category" name="workforce_category" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">—</option>
            @foreach ($workforceCategories as $value => $label)
                <option value="{{ $value }}" @selected($old('workforce_category') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('workforce_category')" class="mt-1" />
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
        <p class="mt-1 text-xs text-gray-500">The employee's manager is inferred automatically from this supervisor's own chain - there's no separate manager field to fill in.</p>
        <x-input-error :messages="$errors->get('supervisor_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="technical_background" value="Technical Background" />
        <x-text-input id="technical_background" name="technical_background" class="mt-1 block w-full" value="{{ $old('technical_background') }}" />
        <x-input-error :messages="$errors->get('technical_background')" class="mt-1" />
    </div>
</div>

<div class="mt-6">
    <x-input-label for="notes" value="Notes / Remarks" />
    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('notes') }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
</div>
