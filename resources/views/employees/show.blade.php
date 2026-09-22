<x-app-layout>
    <x-slot name="header">Employee Profile</x-slot>

    <div x-data="{ tab: '{{ request('tab', session('activeTab', 'overview')) }}' }" class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        <div class="flex flex-col justify-between gap-4 rounded-lg border border-neutral-200 bg-white p-6 sm:flex-row sm:items-center">
            <div class="flex items-center gap-4">
                @if ($employee->photo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}" alt="{{ $employee->full_name }}" class="h-16 w-16 rounded-full object-cover">
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-neutral-100 text-lg font-semibold text-neutral-500">
                        {{ \Illuminate\Support\Str::of($employee->full_name)->explode(' ')->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->take(2)->implode('') }}
                    </span>
                @endif
                <div>
                    <h2 class="text-xl font-semibold text-neutral-900">{{ $employee->full_name }}</h2>
                    <p class="text-sm text-neutral-500">{{ $employee->employee_number }} &middot; {{ $employee->position?->title ?? 'No position assigned' }}</p>
                </div>
            </div>

            <div class="flex gap-2">
                @can('update', $employee)
                    <a href="{{ route('employees.edit', $employee) }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Edit</a>
                @endcan
                <a href="{{ route('employees.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Back to List</a>
            </div>
        </div>

        @can('update', $employee)
            <div class="rounded-lg border border-brand-200 bg-brand-50 p-4 sm:p-6" x-data="{ copied: false }">
                @if ($employee->onboardingLinkIsActive())
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <canvas data-qrcode="{{ route('onboarding.edit', $employee->profile_completion_token) }}"
                                class="h-[176px] w-[176px] shrink-0 rounded-md border border-neutral-200 bg-white"></canvas>

                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-semibold text-neutral-900">New employee onboarding</h3>
                            <p class="mt-1 text-sm text-neutral-600">
                                Scan this QR code, or share the link below, so {{ $employee->full_name }} can fill in the rest of their own profile (photo, contact details, education, ...).
                            </p>

                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input type="text" readonly id="onboarding-link-{{ $employee->id }}"
                                       value="{{ route('onboarding.edit', $employee->profile_completion_token) }}"
                                       class="w-full min-w-0 flex-1 rounded-md border-neutral-300 bg-white text-sm text-neutral-700"
                                       onclick="this.select()">
                                <button type="button"
                                        @click="navigator.clipboard.writeText(document.getElementById('onboarding-link-{{ $employee->id }}').value); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="shrink-0 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                    <span x-show="!copied">Copy Link</span>
                                    <span x-show="copied" x-cloak>Copied!</span>
                                </button>
                            </div>

                            <p class="mt-2 text-xs text-neutral-500">
                                Expires {{ $employee->profile_completion_expires_at->format('d M Y') }}.
                                <form method="POST" action="{{ route('employees.onboarding-link', $employee) }}" class="inline" onsubmit="return confirm('Generate a new link? The current QR code and link will stop working.');">
                                    @csrf
                                    <button type="submit" class="font-medium text-brand-700 hover:underline">Generate new link</button>
                                </form>
                            </p>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-neutral-900">New employee onboarding</h3>
                            <p class="mt-1 text-sm text-neutral-600">
                                @if ($employee->profile_completed_at)
                                    {{ $employee->full_name }} completed their profile via the onboarding link on {{ $employee->profile_completed_at->format('d M Y') }}.
                                @else
                                    Generate a QR code / link so {{ $employee->full_name }} can fill in the rest of their own profile.
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('employees.onboarding-link', $employee) }}">
                            @csrf
                            <button type="submit" class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                                Generate {{ $employee->profile_completed_at ? 'New' : '' }} Onboarding Link
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @endcan

        @can('view', $employee)
            <div class="rounded-lg border border-neutral-200 bg-white p-4 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <canvas data-qrcode="{{ route('verify.employee', $employee) }}"
                            class="h-[176px] w-[176px] shrink-0 rounded-md border border-neutral-200 bg-white"></canvas>

                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-semibold text-neutral-900">Certificate verification QR code</h3>
                        <p class="mt-1 text-sm text-neutral-600">
                            Scan this QR code, or share the link below, to view {{ $employee->full_name }}'s verified certificates - no login required. Print it on an ID badge or post it near their work station.
                        </p>

                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <input type="text" readonly id="verify-link-{{ $employee->id }}"
                                   value="{{ route('verify.employee', $employee) }}"
                                   class="w-full min-w-0 flex-1 rounded-md border-neutral-300 bg-white text-sm text-neutral-700"
                                   onclick="this.select()">
                            <button type="button"
                                    x-data="{ copied: false }"
                                    @click="navigator.clipboard.writeText(document.getElementById('verify-link-{{ $employee->id }}').value); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="shrink-0 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                <span x-show="!copied">Copy Link</span>
                                <span x-show="copied" x-cloak>Copied!</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        @php
            $trainingAssignments = \App\Models\TrainingParticipant::where('employee_id', $employee->id)
                ->with(['trainingSession.trainingProgram', 'certificate'])->latest('id')->get();
            $isOwnProfile = auth()->id() !== null && $employee->user_id === auth()->id();
        @endphp
        @if ($trainingAssignments->isNotEmpty() && ($isOwnProfile || auth()->user()->can('update', $employee)))
            <div class="rounded-lg border border-neutral-200 border-t-2 border-t-accent-500 bg-white p-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-accent-700">Assigned trainings &amp; quiz</h3>
                <ul class="mt-2 divide-y divide-neutral-100 text-sm">
                    @foreach ($trainingAssignments as $assignment)
                        @php [$canQuiz, $why] = $assignment->quizEligibility(); @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <div>
                                <p class="font-medium text-neutral-900">{{ $assignment->trainingSession->trainingProgram->title }}</p>
                                <p class="text-xs text-neutral-500">
                                    {{ $assignment->trainingSession->start_date->format('d M Y') }} &middot; attendance: {{ $assignment->attendance_status }}
                                    @if ($assignment->quiz_submitted_at) &middot; score {{ $assignment->quiz_score }} @endif
                                </p>
                            </div>
                            @if ($isOwnProfile)
                                @if ($canQuiz)
                                    <a href="{{ route('training.quiz.show', $assignment) }}" class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700">Take Quiz</a>
                                @elseif ($assignment->quiz_submitted_at)
                                    <a href="{{ route('training.quiz.show', $assignment) }}" class="text-xs font-medium text-brand-700 hover:underline">{{ $assignment->certificate_id ? 'View result & certificate' : 'View result' }}</a>
                                @else
                                    <span class="text-xs text-neutral-500">{{ \App\Models\TrainingParticipant::quizMessage($why, $assignment->trainingSession) }}</span>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="border-b border-neutral-200">
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
                            :class="tab === '{{ $key }}' ? 'border-brand-500 text-brand-600' : 'border-transparent text-neutral-500 hover:text-neutral-700'"
                            class="border-b-2 px-1 py-3">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        <div x-show="tab === 'overview'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-profile-field label="Employee Number" :value="$employee->employee_number" />
                <x-profile-field label="Nomor LOTOTO" :value="$employee->lototo_number" />
                <x-profile-field label="ID SAP" :value="$employee->sap_id" />
                <x-profile-field label="Nomor Lisensi" :value="$employee->license_number" />
                <x-profile-field label="Email" :value="$employee->email" />
                <x-profile-field label="Phone" :value="$employee->phone" />
                <x-profile-field label="Gender" :value="$employee->gender" />
                <x-profile-field label="Date of Birth" :value="$employee->date_of_birth?->format('d M Y')" />
                <x-profile-field label="Department" :value="$employee->department?->name" />
                <x-profile-field label="Business Unit" :value="$employee->businessUnit?->name" />
                <x-profile-field label="Maintenance Team" :value="$employee->maintenanceTeam?->name" />
                <x-profile-field label="Position" :value="$employee->position?->title" />
                <x-profile-field label="Skill Position" :value="$employee->skillPosition?->name" />
                <x-profile-field label="Employment Type" :value="$employee->employmentType?->name" />
                <x-profile-field label="Employment Source" :value="$employee->employmentSource?->name" />
                <x-profile-field label="Management" :value="\App\Models\Employee::WORKFORCE_CATEGORIES[$employee->workforce_category] ?? null" />
                <x-profile-field label="Employment Status" :value="$employee->employmentStatus?->name" />
                <x-profile-field label="Shift" :value="$employee->shift?->name" />
                <x-profile-field label="Date Joined" :value="$employee->date_joined?->format('d M Y')" />
                <x-profile-field label="Supervisor" :value="$employee->supervisor?->full_name" />
                <x-profile-field label="Manager" :value="$employee->inferredManager()?->full_name" />
                <x-profile-field label="Technical Background" :value="$employee->technical_background" />
            </div>
        </div>

        <div x-show="tab === 'notes'">
            <div class="rounded-lg border border-neutral-200 bg-white p-6">
                @if ($employee->notes)
                    <p class="whitespace-pre-line text-sm text-neutral-700">{{ $employee->notes }}</p>
                @else
                    <p class="text-sm text-neutral-400">No notes recorded for this employee.</p>
                @endif
            </div>
        </div>

        <div x-show="tab === 'skills'">
            <div class="space-y-6">
                <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="border-b-2 border-brand-500 bg-brand-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Skill</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Current Level</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Assessed On</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Assessed By</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($currentSkillLevels as $assessment)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $assessment->skill->name }}</td>
                                    <td class="px-3 py-2 text-neutral-700">{{ $assessment->competencyLevel->level_number }} — {{ $assessment->competencyLevel->name }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $assessment->assessment_date->format('d M Y') }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $assessment->assessedBy?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right">
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
                                    <td colspan="5" class="px-4 py-8 text-center text-neutral-500">No skills assessed yet for this employee.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can(\App\Enums\PermissionName::AssessCompetencies->value)
                    <div class="max-w-lg rounded-lg border border-neutral-200 bg-white p-6">
                        <h3 class="text-sm font-semibold text-neutral-900">Record a Skill Assessment</h3>
                        <form method="POST" action="{{ route('employees.skill-assessments.store', $employee) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="skill_id" value="Skill" />
                                <select id="skill_id" name="skill_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                                    @foreach ($skills as $skill)
                                        <option value="{{ $skill->id }}">{{ $skill->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="competency_level_id" value="Competency Level" />
                                <select id="competency_level_id" name="competency_level_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
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
                                <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 text-sm"></textarea>
                            </div>
                            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Save Assessment</button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div x-show="tab === 'skill-matrix'">
            @php $gapRows = $employee->skillGapRows(); @endphp
            <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="border-b-2 border-brand-500 bg-brand-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Skill</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Current</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Required</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($gapRows as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium text-neutral-900">{{ $row['skill']->name }}</td>
                                <td class="px-3 py-2 text-neutral-700">{{ $row['current'] ? $row['current']->level_number.' — '.$row['current']->name : '—' }}</td>
                                <td class="px-3 py-2 text-neutral-700">{{ $row['required']->level_number }} — {{ $row['required']->name }}</td>
                                <td class="px-3 py-2">
                                    <x-gap-status-badge :status="$row['status']" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-neutral-500">No skill requirements defined for this employee's position.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'job-description'">
            @php $currentJd = $employee->position?->currentJobDescription(); @endphp
            @if ($currentJd)
                <div class="rounded-lg border border-neutral-200 bg-white p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-neutral-900">{{ $currentJd->job_title }}</h3>
                            <p class="text-sm text-neutral-500">Version {{ $currentJd->version }} &middot; Active since {{ $currentJd->effective_date?->format('d M Y') ?? '—' }}</p>
                        </div>
                        <a href="{{ route('job-descriptions.show', $currentJd) }}" class="text-sm text-neutral-600 hover:underline">View full details &rarr;</a>
                    </div>
                    <p class="mt-4 whitespace-pre-line text-sm text-neutral-700">{{ $currentJd->job_purpose }}</p>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center">
                    <p class="text-sm font-medium text-neutral-500">No active job description for this position yet.</p>
                    @can(\App\Enums\PermissionName::ManageJobDescriptions->value)
                        <a href="{{ route('job-descriptions.create') }}" class="mt-2 inline-block text-sm text-neutral-600 hover:underline">Create one &rarr;</a>
                    @endcan
                </div>
            @endif
        </div>

        @php
        $completionStyles = ['completed' => 'bg-green-100 text-green-800', 'incomplete' => 'bg-amber-100 text-amber-800', 'failed' => 'bg-red-100 text-red-800'];
        $planStatusStyles = ['not_started' => 'bg-neutral-100 text-neutral-600', 'in_progress' => 'bg-accent-100 text-accent-800', 'completed' => 'bg-green-100 text-green-800', 'on_hold' => 'bg-amber-100 text-amber-800'];
        @endphp

        <div x-show="tab === 'training'">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-neutral-500">Total training hours recorded: {{ $employee->trainingRecords->sum('duration_hours') ?: 0 }}</p>
                    @can(\App\Enums\PermissionName::ManageTrainingRecords->value)
                        <a href="{{ route('employees.training-records.create', $employee) }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add Training Record</a>
                    @endcan
                </div>

                <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="border-b-2 border-brand-500 bg-brand-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Program</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Hours</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Completion</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Certificate</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($employee->trainingRecords as $record)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $record->trainingProgram?->title ?? 'Ad-hoc training' }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $record->training_date->format('d M Y') }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $record->duration_hours ?? '—' }}</td>
                                    <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $completionStyles[$record->completion_status] }}">{{ ucfirst($record->completion_status) }}</span></td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $record->certificate_issued ? ($record->certificate_reference ?? 'Yes') : '—' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @can(\App\Enums\PermissionName::ManageTrainingRecords->value)
                                            <form method="POST" action="{{ route('employees.training-records.destroy', [$employee, $record]) }}" onsubmit="return confirm('Remove this training record?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Remove</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-neutral-500">No training records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div x-show="tab === 'development'">
            <div class="space-y-4">
                @can(\App\Enums\PermissionName::ManageDevelopmentPlans->value)
                    <div class="flex justify-end">
                        <a href="{{ route('employees.development-plans.create', $employee) }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add Development Plan</a>
                    </div>
                @endcan

                @forelse ($employee->developmentPlans as $plan)
                    <div class="rounded-lg border border-neutral-200 bg-white p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-medium text-neutral-900">{{ $plan->development_objective }}</p>
                                <p class="mt-1 text-sm text-neutral-500">
                                    {{ \App\Models\EmployeeDevelopmentPlan::ACTIONS[$plan->development_action] }}
                                    @if ($plan->relatedSkill) &middot; {{ $plan->relatedSkill->name }} @endif
                                    @if ($plan->mentor) &middot; Mentor: {{ $plan->mentor->full_name }} @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $planStatusStyles[$plan->status] }}">{{ ucwords(str_replace('_', ' ', $plan->status)) }}</span>
                                @can(\App\Enums\PermissionName::ManageDevelopmentPlans->value)
                                    <a href="{{ route('employees.development-plans.edit', [$employee, $plan]) }}" class="text-sm text-neutral-600 hover:underline">Edit</a>
                                @endcan
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="h-2 w-full rounded-full bg-neutral-100">
                                <div class="h-2 rounded-full bg-brand-600" style="width: {{ $plan->progress_percentage }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-neutral-500">{{ $plan->progress_percentage }}% complete &middot; Priority: {{ ucfirst($plan->priority) }} @if ($plan->target_completion_date) &middot; Target: {{ $plan->target_completion_date->format('d M Y') }} @endif</p>
                        </div>
                        @if ($plan->manager_remarks)
                            <p class="mt-2 text-sm text-neutral-600"><span class="font-medium">Manager:</span> {{ $plan->manager_remarks }}</p>
                        @endif
                        @if ($plan->employee_remarks)
                            <p class="mt-1 text-sm text-neutral-600"><span class="font-medium">Employee:</span> {{ $plan->employee_remarks }}</p>
                        @endif
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center text-sm text-neutral-500">
                        No development plans recorded for this employee yet.
                    </div>
                @endforelse
            </div>
        </div>

        @php
        $certStatusStyles = [
            'valid' => 'bg-green-100 text-green-800',
            'expiring_soon' => 'bg-amber-100 text-amber-800',
            'expired' => 'bg-red-100 text-red-800',
            'no_expiry' => 'bg-neutral-100 text-neutral-600',
            'pending_verification' => 'bg-accent-100 text-accent-800',
        ];
        $certStatusLabels = \App\Models\Certificate::statusLabels();
        @endphp

        <div x-show="tab === 'certificates'">
            <div class="space-y-4">
                @can(\App\Enums\PermissionName::ManageCertificates->value)
                    <div class="flex justify-end">
                        <a href="{{ route('employees.certificates.create', $employee) }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add Certificate</a>
                    </div>
                @endcan

                <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="border-b-2 border-brand-500 bg-brand-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Certificate</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Type</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Expiry Date</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($employee->certificates as $certificate)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $certificate->name }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $certificate->certificateType?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-neutral-600">{{ $certificate->expiry_date?->format('d M Y') ?? '—' }}</td>
                                    <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $certStatusStyles[$certificate->status()] }}">{{ $certStatusLabels[$certificate->status()] }}</span></td>
                                    <td class="px-3 py-2 text-right space-x-3">
                                        <a href="{{ route('certificates.show', $certificate) }}" target="_blank" class="font-medium text-brand-700 hover:underline">View Certificate</a>
                                        @if ($certificate->file_path)
                                            <a href="{{ route('certificates.download', $certificate) }}" class="ml-3 text-neutral-600 hover:underline">Download</a>
                                        @endif
                                        @can(\App\Enums\PermissionName::ManageCertificates->value)
                                            <a href="{{ route('employees.certificates.edit', [$employee, $certificate]) }}" class="text-neutral-600 hover:underline">Edit</a>
                                            <form method="POST" action="{{ route('employees.certificates.destroy', [$employee, $certificate]) }}" class="inline" onsubmit="return confirm('Remove this certificate?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Remove</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-neutral-500">No certificates recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
