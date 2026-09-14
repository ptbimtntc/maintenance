<x-app-layout>
    <x-slot name="header">Employee Profile</x-slot>

    <div x-data="{ tab: 'overview' }" class="space-y-6">
        <div class="flex flex-col justify-between gap-4 rounded-lg border border-gray-200 bg-white p-6 sm:flex-row sm:items-center">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ $employee->full_name }}</h2>
                <p class="text-sm text-gray-500">{{ $employee->employee_number }} &middot; {{ $employee->position?->title ?? 'No position assigned' }}</p>
            </div>

            <div class="flex gap-2">
                @can('update', $employee)
                    <a href="{{ route('employees.edit', $employee) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                @endcan
                <a href="{{ route('employees.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back to List</a>
            </div>
        </div>

        <div class="border-b border-gray-200">
            <nav class="-mb-px flex flex-wrap gap-4 text-sm font-medium">
                @foreach ([
                    'overview' => 'Overview',
                    'job-description' => 'Job Description',
                    'skills' => 'Skills & Competencies',
                    'skill-matrix' => 'Skill Matrix',
                    'training' => 'Training Records',
                    'certificates' => 'Certificates',
                    'development' => 'Development History',
                    'notes' => 'Notes / Remarks',
                ] as $key => $label)
                    <button type="button" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-slate-900 text-slate-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="border-b-2 px-1 py-3">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        <div x-show="tab === 'overview'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-profile-field label="Employee Number" :value="$employee->employee_number" />
                <x-profile-field label="Preferred Name" :value="$employee->preferred_name" />
                <x-profile-field label="Email" :value="$employee->email" />
                <x-profile-field label="Phone" :value="$employee->phone" />
                <x-profile-field label="Gender" :value="$employee->gender" />
                <x-profile-field label="Date of Birth" :value="$employee->date_of_birth?->format('d M Y')" />
                <x-profile-field label="Department" :value="$employee->department?->name" />
                <x-profile-field label="Division" :value="$employee->division?->name" />
                <x-profile-field label="Maintenance Area" :value="$employee->maintenanceArea?->name" />
                <x-profile-field label="Maintenance Team" :value="$employee->maintenanceTeam?->name" />
                <x-profile-field label="Position" :value="$employee->position?->title" />
                <x-profile-field label="Employment Type" :value="$employee->employmentType?->name" />
                <x-profile-field label="Employment Status" :value="$employee->employmentStatus?->name" />
                <x-profile-field label="Work Location" :value="$employee->location?->name" />
                <x-profile-field label="Shift" :value="$employee->shift?->name" />
                <x-profile-field label="Date Joined" :value="$employee->date_joined?->format('d M Y')" />
                <x-profile-field label="Supervisor" :value="$employee->supervisor?->full_name" />
                <x-profile-field label="Manager" :value="$employee->manager?->full_name" />
                <x-profile-field label="Education" :value="$employee->education" />
                <x-profile-field label="Technical Background" :value="$employee->technical_background" />
                <x-profile-field label="Years of Experience" :value="$employee->years_of_experience" />
            </div>
        </div>

        <div x-show="tab === 'notes'">
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                @if ($employee->notes)
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $employee->notes }}</p>
                @else
                    <p class="text-sm text-gray-400">No notes recorded for this employee.</p>
                @endif
            </div>
        </div>

        @foreach (['job-description' => 'Job Descriptions', 'skills' => 'Skills & Competency Management', 'skill-matrix' => 'Skill Matrix', 'training' => 'Training Management', 'certificates' => 'Certificate Management', 'development' => 'Employee Development Plans'] as $key => $moduleName)
            <div x-show="tab === '{{ $key }}'">
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center">
                    <p class="text-sm font-medium text-gray-500">{{ $moduleName }} module not yet implemented</p>
                    <p class="mt-1 text-xs text-gray-400">This tab will show real data once the module is built in a later phase.</p>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
