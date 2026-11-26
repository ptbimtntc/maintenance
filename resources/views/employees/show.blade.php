<x-app-layout>
    <x-slot name="header">Employee Profile</x-slot>

    @php
        // This page is linked to from many places (employees list, org
        // chart, skill matrix, competency gap analysis, training records,
        // development plans, certificates, recertification) - a bare route
        // name can't tell those apart, so the originating page passes
        // ?from=... and "Back" uses it instead of always assuming the
        // employees list. See organization/index.blade.php for the same
        // pattern used elsewhere in this app.
        $backDestinations = [
            'org-chart' => ['route' => route('organization-chart.index'), 'label' => 'Back to Organization Chart'],
            'skill-matrix' => ['route' => route('skill-matrix.index'), 'label' => 'Back to Skill Matrix'],
            'competency-gap' => ['route' => route('competency-gap-analysis.index'), 'label' => 'Back to Competency Gap Analysis'],
            'training-records' => ['route' => route('training.records.index'), 'label' => 'Back to Training Records'],
            'development-plans' => ['route' => route('development-plans.index'), 'label' => 'Back to Development Plans'],
            'certificates' => ['route' => route('certificates.index'), 'label' => 'Back to Certificates'],
            'recertification' => ['route' => route('certificates.recertification'), 'label' => 'Back to Recertification'],
        ];
        $from = request('from');
        $back = $backDestinations[$from] ?? ['route' => route('employees.index'), 'label' => 'Back to List'];
    @endphp

    <div x-data="{ tab: '{{ request('tab', session('activeTab', 'overview')) }}' }" class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif
        {{-- Profile header: warm brand banner (same palette as the Employees gallery) with the
             photo blurred behind it, drifting on scroll (data-parallax, resources/js/motion.js). --}}
        @php
            $heroPhoto = \App\Support\LototoEmployeePhoto::url($employee, 'employees.photo');
            $status = $employee->employmentStatus;
            $chips = array_filter([
                'Skill Position' => $employee->skillPosition?->name,
                'Business Unit' => $employee->businessUnit?->name,
                'Supervisor' => $employee->supervisor?->full_name,
                'Shift' => $employee->shift?->name,
            ]);
        @endphp
        <div data-motion class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-brand-50 via-white to-accent-50 shadow-md">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
                @if ($heroPhoto)
                    <img data-parallax src="{{ $heroPhoto }}" alt="" class="absolute inset-x-0 -top-[10%] h-[120%] w-full scale-110 object-cover opacity-25 blur-2xl">
                @endif
                <div class="motion-blob absolute -left-16 -top-24 h-72 w-72 rounded-full bg-brand-200/50 blur-3xl"></div>
                <div class="motion-blob motion-blob--slow absolute -bottom-24 right-0 h-72 w-72 rounded-full bg-accent-200/50 blur-3xl"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-white/70 via-white/40 to-transparent"></div>
            </div>

            <div class="relative flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:p-8">
                <div class="motion-enter h-36 w-28 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-brand-100 to-accent-100 shadow-xl ring-4 ring-white sm:h-44 sm:w-36">
                    <x-employee-avatar :employee="$employee" img-class="h-full w-full object-cover"
                        fallback-class="flex h-full w-full items-center justify-center text-3xl font-bold text-brand-700" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="motion-enter flex flex-wrap items-center gap-2" style="--d: 60ms">
                        <span class="rounded-full bg-white/80 px-2.5 py-0.5 text-xs font-semibold tracking-wide text-brand-700 ring-1 ring-brand-200">{{ $employee->employee_number }}</span>
                        @if ($status)
                            <span @class(['rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1', $status->code === 'ACTIVE' ? 'bg-success-50 text-success-700 ring-success-200' : 'bg-neutral-100 text-neutral-600 ring-neutral-200'])>{{ $status->name }}</span>
                        @endif
                    </div>
                    <h2 class="motion-enter mt-2 text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl" style="--d: 120ms">{{ $employee->full_name }}</h2>
                    <p class="motion-enter mt-1 text-sm text-neutral-600" style="--d: 180ms">{{ $employee->position?->title ?? 'No position assigned' }}</p>

                    @if ($chips)
                        <dl class="motion-enter mt-4 flex flex-wrap gap-2" style="--d: 240ms">
                            @foreach ($chips as $label => $value)
                                <div class="rounded-xl bg-white/80 px-3 py-1.5 shadow-sm ring-1 ring-neutral-200 backdrop-blur">
                                    <dt class="text-[10px] font-medium uppercase tracking-wide text-neutral-400">{{ $label }}</dt>
                                    <dd class="text-xs font-semibold text-neutral-800">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>

                <div class="flex shrink-0 gap-2 sm:flex-col sm:self-start">
                    @can('update', $employee)
                        <x-button variant="secondary" href="{{ route('employees.edit', array_filter(['employee' => $employee, 'from' => $from])) }}">Edit</x-button>
                    @endcan
                    <x-button variant="secondary" href="{{ $back['route'] }}">{{ $back['label'] }}</x-button>
                </div>
            </div>

            {{-- QR codes open in overlays instead of taking up the page. --}}
            <div class="relative flex flex-wrap gap-2 border-t border-white/70 bg-white/50 px-6 py-3 backdrop-blur sm:px-8">
                <x-button variant="secondary" size="sm" type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'certificate-qr')">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" /></svg>
                    Certificate QR
                </x-button>
                @can('update', $employee)
                    <x-button variant="secondary" size="sm" type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'onboarding-qr')">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>
                        Onboarding
                        @if ($employee->onboardingLinkIsActive())
                            <span class="h-2 w-2 rounded-full bg-success-500" title="Link active"></span>
                        @elseif ($employee->profile_completed_at)
                            <span class="text-[10px] font-semibold text-success-700">Done</span>
                        @endif
                    </x-button>
                @endcan
            </div>
        </div>

        @php
            $trainingAssignments = \App\Models\TrainingParticipant::where('employee_id', $employee->id)
                ->with(['trainingSession.trainingProgram', 'certificate'])->latest('id')->get();
            $isOwnProfile = auth()->id() !== null && $employee->user_id === auth()->id();
        @endphp
        @if ($trainingAssignments->isNotEmpty() && ($isOwnProfile || auth()->user()->can('update', $employee)))
            <div class="rounded-2xl border border-neutral-200 border-t-2 border-t-accent-500 bg-white p-4 shadow-sm sm:px-6">
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
                                    <x-button size="xs" href="{{ route('training.quiz.show', $assignment) }}">Take Quiz</x-button>
                                @elseif ($assignment->quiz_submitted_at)
                                    <a href="{{ route('training.quiz.show', $assignment) }}" class="text-xs font-medium text-accent-700 hover:underline">{{ $assignment->certificate_id ? 'View result & certificate' : 'View result' }}</a>
                                @else
                                    <span class="text-xs text-neutral-500">{{ \App\Models\TrainingParticipant::quizMessage($why, $assignment->trainingSession) }}</span>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Tabs styled like the Employees Gallery | Table switch; scrolls sideways on phones. --}}
        <div class="-mx-1 overflow-x-auto px-1 pb-1">
            <nav class="inline-flex min-w-max gap-0.5 rounded-xl border border-neutral-200 bg-white p-1 text-sm font-medium shadow-sm" aria-label="Profile sections">
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
                            :class="tab === '{{ $key }}' ? 'bg-neutral-900 text-white shadow-sm' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'"
                            :aria-current="tab === '{{ $key }}' ? 'page' : null"
                            class="whitespace-nowrap rounded-lg px-3 py-1.5 transition">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
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
            <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
                @if ($employee->notes)
                    <p class="whitespace-pre-line text-sm text-neutral-700">{{ $employee->notes }}</p>
                @else
                    <p class="text-sm text-neutral-400">No notes recorded for this employee.</p>
                @endif
            </div>
        </div>

        <div x-show="tab === 'skills'">
            <div class="space-y-6">
                @php $checklistCount = count($employee->checklistSkillIds()); @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-3 shadow-md">
                    <div>
                        <p class="text-sm font-semibold text-neutral-900">Skill Checklist</p>
                        <p class="text-xs text-neutral-500">{{ $checklistCount }} skill(s) apply to this employee{{ $employee->position ? ' (default from '.$employee->position->title.', adjusted by supervisor)' : '' }}.</p>
                    </div>
                    @can(\App\Enums\PermissionName::AssessCompetencies->value)
                        <x-button variant="secondary" href="{{ route('employees.skill-checklist.edit', $employee) }}">Review checklist</x-button>
                    @endcan
                </div>

                <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-md">
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
                                            <form method="POST" action="{{ route('employees.skill-assessments.destroy', [$employee, $assessment]) }}" data-confirm="Remove this assessment record?">
                                                @csrf
                                                @method('DELETE')
                                                @if ($from)
                                                    <input type="hidden" name="from" value="{{ $from }}">
                                                @endif
                                                <button type="submit" class="text-danger-600 hover:underline">Remove</button>
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
                    <div class="max-w-lg rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
                        <h3 class="text-sm font-semibold text-neutral-900">Record a Skill Assessment</h3>
                        <form method="POST" action="{{ route('employees.skill-assessments.store', $employee) }}" class="mt-4 space-y-4">
                            @if ($from)
                                <input type="hidden" name="from" value="{{ $from }}">
                            @endif
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
                            <x-button type="submit">Save Assessment</x-button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div x-show="tab === 'skill-matrix'">
            @php $gapRows = $employee->skillGapRows(); @endphp
            <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-md">
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
                <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
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
        $completionStyles = ['completed' => 'bg-success-100 text-success-800', 'incomplete' => 'bg-warning-100 text-warning-800', 'failed' => 'bg-danger-100 text-danger-800'];
        $planStatusStyles = ['not_started' => 'bg-neutral-100 text-neutral-600', 'in_progress' => 'bg-accent-100 text-accent-800', 'completed' => 'bg-success-100 text-success-800', 'on_hold' => 'bg-warning-100 text-warning-800'];
        @endphp

        <div x-show="tab === 'training'">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-neutral-500">Total training hours recorded: {{ $employee->trainingRecords->sum('duration_hours') ?: 0 }}</p>
                    @can(\App\Enums\PermissionName::ManageTrainingRecords->value)
                        <x-button href="{{ route('employees.training-records.create', array_filter(['employee' => $employee, 'from' => $from])) }}">Add Training Record</x-button>
                    @endcan
                </div>

                <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-md">
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
                                            <form method="POST" action="{{ route('employees.training-records.destroy', [$employee, $record]) }}" data-confirm="Remove this training record?">
                                                @csrf
                                                @method('DELETE')
                                                @if ($from)
                                                    <input type="hidden" name="from" value="{{ $from }}">
                                                @endif
                                                <button type="submit" class="text-danger-600 hover:underline">Remove</button>
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
                        <x-button href="{{ route('employees.development-plans.create', array_filter(['employee' => $employee, 'from' => $from])) }}">Add Development Plan</x-button>
                    </div>
                @endcan

                @forelse ($employee->developmentPlans as $plan)
                    <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
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
                                    <a href="{{ route('employees.development-plans.edit', array_filter(['employee' => $employee, 'plan' => $plan, 'from' => $from])) }}" class="text-sm text-neutral-600 hover:underline">Edit</a>
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
            'valid' => 'bg-success-100 text-success-800',
            'expiring_soon' => 'bg-warning-100 text-warning-800',
            'expired' => 'bg-danger-100 text-danger-800',
            'no_expiry' => 'bg-neutral-100 text-neutral-600',
            'pending_verification' => 'bg-accent-100 text-accent-800',
        ];
        $certStatusLabels = \App\Models\Certificate::statusLabels();
        @endphp

        <div x-show="tab === 'certificates'">
            <div class="space-y-4">
                @can(\App\Enums\PermissionName::ManageCertificates->value)
                    <div class="flex justify-end">
                        <x-button href="{{ route('employees.certificates.create', array_filter(['employee' => $employee, 'from' => $from])) }}">Add Certificate</x-button>
                    </div>
                @endcan

                <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-md">
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
                                        <a href="{{ route('certificates.show', $certificate) }}" target="_blank" class="font-medium text-accent-700 hover:underline">View Certificate</a>
                                        @if ($certificate->file_path)
                                            <a href="{{ route('certificates.download', $certificate) }}" class="ml-3 text-neutral-600 hover:underline">Download</a>
                                        @endif
                                        @can(\App\Enums\PermissionName::ManageCertificates->value)
                                            <a href="{{ route('employees.certificates.edit', array_filter(['employee' => $employee, 'certificate' => $certificate, 'from' => $from])) }}" class="text-neutral-600 hover:underline">Edit</a>
                                            <form method="POST" action="{{ route('employees.certificates.destroy', [$employee, $certificate]) }}" class="inline" data-confirm="Remove this certificate?">
                                                @csrf
                                                @method('DELETE')
                                                @if ($from)
                                                    <input type="hidden" name="from" value="{{ $from }}">
                                                @endif
                                                <button type="submit" class="text-danger-600 hover:underline">Remove</button>
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
    </div>

    <x-modal name="certificate-qr" maxWidth="md">
        <div class="p-6 text-center" x-data="{ copied: false }">
            <h2 class="text-base font-semibold text-neutral-900">Certificate verification QR</h2>
            <p class="mt-1 text-sm text-neutral-500">Scan to view {{ $employee->full_name }}'s verified certificates, no login required. Print it on an ID badge or near their work station.</p>
            <canvas data-qrcode="{{ route('verify.employee', $employee) }}" class="mx-auto mt-5 h-[176px] w-[176px] rounded-xl border border-neutral-200 bg-white p-1 shadow-sm"></canvas>
            <div class="mt-5 flex gap-2">
                <input type="text" readonly id="verify-link-{{ $employee->id }}" value="{{ route('verify.employee', $employee) }}"
                       class="w-full min-w-0 flex-1 rounded-md border-neutral-300 bg-neutral-50 text-sm text-neutral-700" onclick="this.select()">
                <x-button variant="secondary" type="button" class="shrink-0" @click="navigator.clipboard.writeText(document.getElementById('verify-link-{{ $employee->id }}').value); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-show="!copied">Copy Link</span><span x-show="copied" x-cloak>Copied!</span>
                </x-button>
            </div>
            <div class="mt-4 flex justify-end">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'certificate-qr')">Close</x-button>
            </div>
        </div>
    </x-modal>

    @can('update', $employee)
        <x-modal name="onboarding-qr" maxWidth="md" :show="session('open_modal') === 'onboarding-qr'">
            <div class="p-6 text-center" x-data="{ copied: false }">
                <h2 class="text-base font-semibold text-neutral-900">New employee onboarding</h2>
                @if ($employee->onboardingLinkIsActive())
                    <p class="mt-1 text-sm text-neutral-500">Scan this QR code, or share the link, so {{ $employee->full_name }} can fill in the rest of their own profile (photo, contact details, education, ...).</p>
                    <canvas data-qrcode="{{ route('onboarding.edit', $employee->profile_completion_token) }}" class="mx-auto mt-5 h-[176px] w-[176px] rounded-xl border border-neutral-200 bg-white p-1 shadow-sm"></canvas>
                    <div class="mt-5 flex gap-2">
                        <input type="text" readonly id="onboarding-link-{{ $employee->id }}" value="{{ route('onboarding.edit', $employee->profile_completion_token) }}"
                               class="w-full min-w-0 flex-1 rounded-md border-neutral-300 bg-neutral-50 text-sm text-neutral-700" onclick="this.select()">
                        <x-button variant="secondary" type="button" class="shrink-0" @click="navigator.clipboard.writeText(document.getElementById('onboarding-link-{{ $employee->id }}').value); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="!copied">Copy Link</span><span x-show="copied" x-cloak>Copied!</span>
                        </x-button>
                    </div>
                    <p class="mt-3 text-xs text-neutral-500">Expires {{ $employee->profile_completion_expires_at->format('d M Y') }}.</p>
                @else
                    <p class="mt-2 text-sm text-neutral-600">
                        @if ($employee->profile_completed_at)
                            {{ $employee->full_name }} completed their profile via the onboarding link on {{ $employee->profile_completed_at->format('d M Y') }}.
                        @else
                            Generate a QR code / link so {{ $employee->full_name }} can fill in the rest of their own profile.
                        @endif
                    </p>
                @endif
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <form method="POST" action="{{ route('employees.onboarding-link', $employee) }}"
                          @if ($employee->onboardingLinkIsActive()) data-confirm="Generate a new link? The current QR code and link will stop working." @endif>
                        @csrf
                        <x-button type="submit" :variant="$employee->onboardingLinkIsActive() ? 'secondary' : 'primary'">
                            {{ $employee->onboardingLinkIsActive() ? 'Generate new link' : 'Generate '.($employee->profile_completed_at ? 'New ' : '').'Onboarding Link' }}
                        </x-button>
                    </form>
                    <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'onboarding-qr')">Close</x-button>
                </div>
            </div>
        </x-modal>
    @endcan
</x-app-layout>
