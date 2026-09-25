@php
$canViewEmployees = auth()->user()->can('viewAny', \App\Models\Employee::class);
$canViewSkillMatrix = auth()->user()->can(\App\Enums\PermissionName::ViewSkillMatrix->value);
$canViewCompetencyGap = auth()->user()->can(\App\Enums\PermissionName::ViewCompetencyGap->value);
$canViewTraining = auth()->user()->can(\App\Enums\PermissionName::ViewTraining->value);
$canViewCertificates = auth()->user()->can(\App\Enums\PermissionName::ViewCertificates->value);
$canViewDevelopmentPlans = auth()->user()->can(\App\Enums\PermissionName::ViewDevelopmentPlans->value);

$icons = [
    'employees' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    'check' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'gap' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
    'certificate' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
    'target' => 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z',
];

$priorityStyles = ['high' => 'bg-danger-100 text-danger-700', 'medium' => 'bg-warning-100 text-warning-700', 'low' => 'bg-neutral-100 text-neutral-600'];
$maxAreaCount = collect($maintenanceAreaDistribution)->max('total') ?: 1;
$maxSkillGap = collect($topSkillGaps)->max('count') ?: 1;
@endphp

<x-app-layout>
    <x-slot name="header">Maintenance People Development Dashboard</x-slot>

    <div class="space-y-5">
        <div class="sticky top-0 z-20 flex flex-wrap items-end justify-between gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
            <div>
                <p class="text-xs text-neutral-500">Build Competent People &middot; Improve Performance &middot; Ensure Reliability</p>
                <p class="mt-0.5 text-xs text-neutral-400">{{ now()->format('l, d M Y') }}</p>
            </div>

            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-[11px] font-medium text-neutral-500">Year</label>
                    <select name="year" onchange="this.form.submit()" class="mt-0.5 rounded-md border-neutral-300 text-sm">
                        @foreach ($filterOptions['years'] as $year)
                            <option value="{{ $year }}" @selected($filters['year'] == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-neutral-500">Business Unit</label>
                    <select name="business_unit_id" onchange="this.form.submit()" class="mt-0.5 rounded-md border-neutral-300 text-sm">
                        <option value="">All Units</option>
                        @foreach ($filterOptions['businessUnits'] as $unit)
                            <option value="{{ $unit->id }}" @selected($filters['business_unit_id'] == $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-neutral-500">Employment Type</label>
                    <select name="employment_type_id" onchange="this.form.submit()" class="mt-0.5 rounded-md border-neutral-300 text-sm">
                        <option value="">All Types</option>
                        @foreach ($filterOptions['employmentTypes'] as $type)
                            <option value="{{ $type->id }}" @selected($filters['employment_type_id'] == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-neutral-500">Employment Source</label>
                    <select name="employment_source_id" onchange="this.form.submit()" class="mt-0.5 rounded-md border-neutral-300 text-sm">
                        <option value="">All Sources</option>
                        @foreach ($filterOptions['employmentSources'] as $source)
                            <option value="{{ $source->id }}" @selected($filters['employment_source_id'] == $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($filters['business_unit_id'] || $filters['employment_type_id'] || $filters['employment_source_id'])
                    <a href="{{ route('dashboard', ['year' => $filters['year']]) }}" class="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50">Clear</a>
                @endif
            </form>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
            <x-dashboard-stat
                label="Total Employees"
                :value="$kpis['total_employees']"
                icon="{{ $icons['employees'] }}"
                color="blue"
                :href="$canViewEmployees ? route('employees.index') : null"
                :chartType="array_sum($employeeTrend) > 0 ? 'sparkline' : null"
                :chartValues="$employeeTrend"
                :chartColors="['#01ADEF']"
                :caption="array_sum($employeeTrend) > 0 ? $filters['year'].' cumulative by join date' : null"
            />
            <x-dashboard-stat
                label="Active Employees"
                :value="$kpis['active_employees']"
                icon="{{ $icons['check'] }}"
                color="green"
                :href="$canViewEmployees ? route('employees.index') : null"
            />
            <x-dashboard-stat
                label="Competency Gap"
                :value="$kpis['competency_gap']"
                icon="{{ $icons['gap'] }}"
                color="amber"
                :href="$canViewCompetencyGap ? route('competency-gap-analysis.index') : null"
            />
            <x-dashboard-stat
                label="Training Hours ({{ $filters['year'] }})"
                :value="rtrim(rtrim(number_format($kpis['training_hours'], 1), '0'), '.')"
                icon="{{ $icons['clock'] }}"
                color="blue"
                :href="$canViewTraining ? route('training.records.index') : null"
                :chartType="array_sum($trainingHoursTrend) > 0 ? 'sparkline' : null"
                :chartValues="$trainingHoursTrend"
                :chartColors="['#2F7532']"
                :caption="array_sum($trainingHoursTrend) > 0 ? 'Monthly hours delivered' : null"
            />
            <x-dashboard-stat
                label="Certificates Expiring"
                :value="$kpis['certificates_expiring']"
                icon="{{ $icons['certificate'] }}"
                color="red"
                :href="$canViewCertificates ? route('certificates.recertification', ['window' => '60']) : null"
            />
            <x-dashboard-stat
                label="Development Plans"
                :value="$kpis['development_plans']"
                icon="{{ $icons['target'] }}"
                color="brand"
                :href="$canViewDevelopmentPlans ? route('development-plans.index') : null"
                :chartType="array_sum($developmentPlansTrend) > 0 ? 'sparkline' : null"
                :chartValues="$developmentPlansTrend"
                :chartColors="['#7c3aed']"
                :caption="array_sum($developmentPlansTrend) > 0 ? 'Plans created per month' : null"
            />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-dashboard-donut
                title="Competency Health Overview"
                :total="array_sum($competencyBreakdown)"
                totalLabel="Employees"
                :segments="[
                    ['label' => 'Meets Requirement', 'value' => $competencyBreakdown['meets'], 'color' => '#2F7532'],
                    ['label' => 'Gap', 'value' => $competencyBreakdown['gap'], 'color' => '#A93624'],
                    ['label' => 'Assessment Incomplete', 'value' => $competencyBreakdown['incomplete'], 'color' => '#F0900A'],
                    ['label' => 'No Requirements Defined', 'value' => $competencyBreakdown['no_requirements'], 'color' => '#B8B2A8'],
                ]"
            />

            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
                <h3 class="mb-3 text-sm font-semibold text-neutral-900">Top Skill Gaps</h3>
                @if ($topSkillGaps->isEmpty())
                    <p class="py-6 text-center text-sm text-neutral-400">No skill gaps found for the current filters.</p>
                @else
                    <div class="space-y-2.5">
                        @foreach ($topSkillGaps as $row)
                            <div class="flex items-center gap-3 text-xs">
                                <span class="w-24 shrink-0 truncate text-neutral-600">{{ $row['skill'] }}</span>
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-100">
                                    <div class="h-full rounded-full bg-accent-500" style="width: {{ round($row['count'] / $maxSkillGap * 100) }}%"></div>
                                </div>
                                <span class="w-6 shrink-0 text-right font-medium text-neutral-900">{{ $row['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md lg:col-span-1">
                <h3 class="mb-3 text-sm font-semibold text-neutral-900">Training &amp; Development ({{ $filters['year'] }})</h3>
                <div class="relative h-56 w-full">
                    <canvas
                        data-chart
                        data-chart-type="combo"
                        data-chart-labels="{{ json_encode($trainingSeries['labels']) }}"
                        data-chart-values="{{ json_encode($trainingSeries['hours']) }}"
                        data-chart-secondary-values="{{ json_encode($trainingSeries['participants']) }}"
                        data-chart-bar-label="Training Hours"
                        data-chart-line-label="Participants"
                    ></canvas>
                </div>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md lg:col-span-1">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-neutral-900">Upcoming Training</h3>
                    @if ($canViewTraining)
                        <a href="{{ route('training.calendar') }}" class="text-xs font-medium text-accent-600 hover:underline">View All &rarr;</a>
                    @endif
                </div>
                @if ($upcomingSessions->isEmpty())
                    <p class="py-6 text-center text-sm text-neutral-400">Nothing scheduled.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($upcomingSessions as $session)
                            <div class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-accent-50 text-accent-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['clock'] }}" />
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-neutral-900">{{ $session->session_title ?: $session->trainingProgram->title }}</p>
                                    <p class="text-xs text-neutral-500">
                                        {{ $session->start_date->format('d M') }}
                                        @if ($session->end_date && $session->end_date->ne($session->start_date))
                                            &ndash; {{ $session->end_date->format('d M Y') }}
                                        @else
                                            {{ $session->start_date->format('Y') }}
                                        @endif
                                        &middot; {{ $session->participants->count() }} participant(s)
                                    </p>
                                </div>
                                @if ($session->trainingProgram->trainingType)
                                    <span class="shrink-0 rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-600">{{ $session->trainingProgram->trainingType->name }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <x-dashboard-donut
                title="Certificate Status"
                :total="$certificateSummary['total']"
                totalLabel="Certificates"
                :segments="[
                    ['label' => 'Valid', 'value' => $certificateSummary['valid'], 'color' => '#2F7532'],
                    ['label' => 'Expiring Soon', 'value' => $certificateSummary['expiring_soon'], 'color' => '#F0900A'],
                    ['label' => 'Expired', 'value' => $certificateSummary['expired'], 'color' => '#A93624'],
                    ['label' => 'Pending Verification', 'value' => $certificateSummary['pending_verification'], 'color' => '#01ADEF'],
                ]"
            />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md lg:col-span-1">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-neutral-900">Development Priority</h3>
                    @if ($canViewDevelopmentPlans)
                        <a href="{{ route('development-plans.index') }}" class="text-xs font-medium text-accent-600 hover:underline">View All &rarr;</a>
                    @endif
                </div>
                @if ($developmentPriorities->isEmpty())
                    <p class="py-6 text-center text-sm text-neutral-400">No open development plans.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-[10px] uppercase tracking-wide text-neutral-400">
                                    <th class="pb-2 pr-2">Employee</th>
                                    <th class="pb-2 pr-2">Skill</th>
                                    <th class="pb-2 pr-2">Progress</th>
                                    <th class="pb-2">Priority</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @foreach ($developmentPriorities as $plan)
                                    <tr>
                                        <td class="py-1.5 pr-2 font-medium text-neutral-800">{{ $plan->employee->full_name }}</td>
                                        <td class="py-1.5 pr-2 text-neutral-500">{{ $plan->relatedSkill?->name ?? '—' }}</td>
                                        <td class="py-1.5 pr-2">
                                            <div class="h-1.5 w-16 overflow-hidden rounded-full bg-neutral-100">
                                                <div class="h-full rounded-full bg-brand-500" style="width: {{ $plan->progress_percentage ?? 0 }}%"></div>
                                            </div>
                                        </td>
                                        <td class="py-1.5">
                                            <span @class(['rounded-full px-2 py-0.5 text-[10px] font-medium', $priorityStyles[$plan->priority] ?? 'bg-neutral-100 text-neutral-600'])>
                                                {{ ucfirst($plan->priority) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <x-dashboard-donut
                title="Workforce Mix"
                :total="$workforceMix['BC'] + $workforceMix['WCM']"
                totalLabel="Employees"
                :segments="[
                    ['label' => 'Blue Collar (BC)', 'value' => $workforceMix['BC'], 'color' => '#01ADEF'],
                    ['label' => 'White Collar Mgmt (WCM)', 'value' => $workforceMix['WCM'], 'color' => '#2F7532'],
                ]"
            />

            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md lg:col-span-1">
                <h3 class="mb-3 text-sm font-semibold text-neutral-900">Maintenance Area Distribution</h3>
                @if ($maintenanceAreaDistribution->isEmpty())
                    <p class="py-6 text-center text-sm text-neutral-400">No employees assigned to a maintenance area yet.</p>
                @else
                    <div class="flex h-40 items-end justify-around gap-2">
                        @foreach ($maintenanceAreaDistribution as $row)
                            <div class="flex flex-1 flex-col items-center gap-1">
                                <span class="text-xs font-semibold text-neutral-900">{{ $row['total'] }}</span>
                                <div class="w-full rounded-t-md bg-brand-500" style="height: {{ max(round($row['total'] / $maxAreaCount * 100), 4) }}%"></div>
                                <span class="w-full truncate text-center text-[10px] text-neutral-500" title="{{ $row['area'] }}">{{ $row['area'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-neutral-900">People Development Insights</h3>
                <span class="text-[11px] text-neutral-400">Last updated {{ now()->format('d M Y H:i') }}</span>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-md border border-warning-100 bg-warning-50 p-3">
                    <p class="text-xl font-bold text-warning-700">{{ $insights['employees_needing_development'] }}</p>
                    <p class="text-xs text-warning-800">employees need development</p>
                    <p class="text-[10px] text-warning-600">{{ $insights['employees_needing_development_pct'] }}% of total workforce</p>
                </div>
                <div class="rounded-md border border-danger-100 bg-danger-50 p-3">
                    <p class="text-xl font-bold text-danger-700">{{ $insights['certificates_needing_renewal'] }}</p>
                    <p class="text-xs text-danger-800">certificates need renewal</p>
                    <p class="text-[10px] text-danger-600">{{ $insights['certificates_needing_renewal_pct'] }}% of total certificates</p>
                </div>
                <div class="rounded-md border border-neutral-200 bg-neutral-50 p-3">
                    <p class="text-xl font-bold text-neutral-700">{{ $insights['assessments_incomplete'] }}</p>
                    <p class="text-xs text-neutral-800">assessments incomplete</p>
                    <p class="text-[10px] text-neutral-500">{{ $insights['assessments_incomplete_pct'] }}% of total employees</p>
                </div>
                <div class="rounded-md border border-brand-100 bg-brand-50 p-3">
                    <p class="text-xl font-bold text-brand-700">{{ $insights['critical_skill_gaps'] }}</p>
                    <p class="text-xs text-brand-800">critical skill gaps</p>
                    <p class="text-[10px] text-brand-600">skills with 3+ employees below requirement</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
