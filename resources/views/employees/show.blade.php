<x-app-layout>
    <x-slot name="header">Employee Profile</x-slot>

    <div x-data="{ tab: '{{ session('activeTab', 'overview') }}' }" class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
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

        <div x-show="tab === 'skills'">
            <div class="space-y-6">
                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Skill</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Current Level</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Assessed On</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Assessed By</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($currentSkillLevels as $assessment)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $assessment->skill->name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $assessment->competencyLevel->level_number }} — {{ $assessment->competencyLevel->name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $assessment->assessment_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $assessment->assessedBy?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @can(\App\Enums\PermissionName::AssessCompetencies->value)
                                            <form method="POST" action="{{ route('employees.skill-assessments.destroy', [$employee, $assessment]) }}" onsubmit="return confirm('Remove this assessment record?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Remove</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No skills assessed yet for this employee.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can(\App\Enums\PermissionName::AssessCompetencies->value)
                    <div class="max-w-lg rounded-lg border border-gray-200 bg-white p-6">
                        <h3 class="text-sm font-semibold text-gray-900">Record a Skill Assessment</h3>
                        <form method="POST" action="{{ route('employees.skill-assessments.store', $employee) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="skill_id" value="Skill" />
                                <select id="skill_id" name="skill_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($skills as $skill)
                                        <option value="{{ $skill->id }}">{{ $skill->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="competency_level_id" value="Competency Level" />
                                <select id="competency_level_id" name="competency_level_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($competencyLevels as $level)
                                        <option value="{{ $level->id }}">{{ $level->level_number }} — {{ $level->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="assessment_date" value="Assessment Date" />
                                <x-text-input id="assessment_date" type="date" name="assessment_date" class="mt-1 block w-full" value="{{ now()->format('Y-m-d') }}" />
                            </div>
                            <div>
                                <x-input-label for="assessment_method" value="Assessment Method (optional)" />
                                <x-text-input id="assessment_method" name="assessment_method" class="mt-1 block w-full" placeholder="e.g. Practical test, Observation" />
                            </div>
                            <div>
                                <x-input-label for="remarks" value="Remarks (optional)" />
                                <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                            </div>
                            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save Assessment</button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div x-show="tab === 'skill-matrix'">
            @php $requirements = $employee->position?->skillRequirements ?? collect(); @endphp
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Skill</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Current</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Required</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($requirements as $requirement)
                            @php
                                $current = $currentSkillLevels->get($requirement->skill_id)?->competencyLevel;
                                $gap = $current ? $requirement->requiredCompetencyLevel->level_number - $current->level_number : null;
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $requirement->skill->name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $current ? $current->level_number.' — '.$current->name : '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $requirement->requiredCompetencyLevel->level_number }} — {{ $requirement->requiredCompetencyLevel->name }}</td>
                                <td class="px-4 py-3">
                                    @if (is_null($current))
                                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Not Assessed</span>
                                    @elseif ($gap > 0)
                                        <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800">Development Required</span>
                                    @elseif ($gap === 0)
                                        <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">Meets Requirement</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">Exceeds Requirement</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">No skill requirements defined for this employee's position.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'job-description'">
            @php $currentJd = $employee->position?->currentJobDescription(); @endphp
            @if ($currentJd)
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $currentJd->job_title }}</h3>
                            <p class="text-sm text-gray-500">Version {{ $currentJd->version }} &middot; Active since {{ $currentJd->effective_date?->format('d M Y') ?? '—' }}</p>
                        </div>
                        <a href="{{ route('job-descriptions.show', $currentJd) }}" class="text-sm text-slate-600 hover:underline">View full details &rarr;</a>
                    </div>
                    <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $currentJd->job_purpose }}</p>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center">
                    <p class="text-sm font-medium text-gray-500">No active job description for this position yet.</p>
                    @can(\App\Enums\PermissionName::ManageJobDescriptions->value)
                        <a href="{{ route('job-descriptions.create') }}" class="mt-2 inline-block text-sm text-slate-600 hover:underline">Create one &rarr;</a>
                    @endcan
                </div>
            @endif
        </div>

        @foreach (['training' => 'Training Management', 'certificates' => 'Certificate Management', 'development' => 'Employee Development Plans'] as $key => $moduleName)
            <div x-show="tab === '{{ $key }}'">
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center">
                    <p class="text-sm font-medium text-gray-500">{{ $moduleName }} module not yet implemented</p>
                    <p class="mt-1 text-xs text-gray-400">This tab will show real data once the module is built in a later phase.</p>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
