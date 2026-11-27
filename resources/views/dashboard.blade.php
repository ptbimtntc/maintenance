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
    'overtime' => 'M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
];

$priorityStyles = ['high' => 'bg-danger-100 text-danger-700', 'medium' => 'bg-warning-100 text-warning-700', 'low' => 'bg-neutral-100 text-neutral-600'];
$maxAreaCount = collect($maintenanceAreaDistribution)->max('total') ?: 1;
$maxSkillGap = collect($topSkillGaps)->max('count') ?: 1;
@endphp


<x-app-layout>
    <x-slot name="header">Maintenance People Development Dashboard</x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-md bg-warning-50 px-4 py-3 text-sm text-warning-800">{{ session('status') }}</div>
    @endif

    @php
        $selectClass = 'mt-0.5 block w-full lg:w-40 rounded-md border-neutral-300 py-1.5 text-sm transition focus:border-brand-500 focus:ring-brand-500';
        $hasFilters = $filters['business_unit_id'] || $filters['employment_type_id'] || $filters['employment_source_id'];
    @endphp

    @php
        $hour = (int) now()->format('G');
        $greeting = $hour < 11 ? 'Good morning' : ($hour < 15 ? 'Good afternoon' : ($hour < 19 ? 'Good evening' : 'Good night'));
        $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->trim()->before(' ');
    @endphp

    {{-- data-motion opts this page into the scroll-reveal / count-up effects (resources/js/motion.js). --}}
    <div class="space-y-6" data-motion>
        {{-- Header + filters. Only sticky on large screens; on phones/tablets
             four stacked selects would eat most of the viewport. --}}
        <div data-reveal class="glass-hero relative z-20 overflow-hidden rounded-lg border border-neutral-200 bg-white p-4 shadow-sm lg:sticky lg:top-0 lg:flex lg:items-end lg:justify-between lg:gap-6">
            {{-- Brand accent strip along the top edge. --}}
            <span aria-hidden="true" class="motion-gradient-text pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-500 via-warning-400 to-accent-500"></span>
            <div class="mb-4 lg:mb-0 lg:shrink-0">
                <p class="text-base font-semibold text-neutral-900">{{ $greeting }}, {{ $firstName }}</p>
                <p class="mt-0.5 text-xs text-neutral-500"><span class="font-medium text-neutral-700">{{ now()->format('l, d F Y') }}</span> &middot; Build Competent People &middot; Improve Performance &middot; Ensure Reliability</p>
            </div>

            <form method="GET" class="grid grid-cols-2 items-end gap-2 sm:grid-cols-4 lg:flex lg:flex-nowrap lg:justify-end">
                <div>
                    <label for="filter-year" class="block text-[11px] font-medium text-neutral-500">Year</label>
                    <select id="filter-year" name="year" onchange="this.form.submit()" class="{{ $selectClass }} lg:!w-24">
                        @foreach ($filterOptions['years'] as $year)
                            <option value="{{ $year }}" @selected($filters['year'] == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-unit" class="block text-[11px] font-medium text-neutral-500">Business Unit</label>
                    <select id="filter-unit" name="business_unit_id" onchange="this.form.submit()" class="{{ $selectClass }}">
                        <option value="">All Units</option>
                        @foreach ($filterOptions['businessUnits'] as $unit)
                            <option value="{{ $unit->id }}" @selected($filters['business_unit_id'] == $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-type" class="block text-[11px] font-medium text-neutral-500">Employment Type</label>
                    <select id="filter-type" name="employment_type_id" onchange="this.form.submit()" class="{{ $selectClass }}">
                        <option value="">All Types</option>
                        @foreach ($filterOptions['employmentTypes'] as $type)
                            <option value="{{ $type->id }}" @selected($filters['employment_type_id'] == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-source" class="block text-[11px] font-medium text-neutral-500">Employment Source</label>
                    <select id="filter-source" name="employment_source_id" onchange="this.form.submit()" class="{{ $selectClass }}">
                        <option value="">All Sources</option>
                        @foreach ($filterOptions['employmentSources'] as $source)
                            <option value="{{ $source->id }}" @selected($filters['employment_source_id'] == $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($hasFilters)
                    <x-button variant="secondary" size="sm" href="{{ route('dashboard', ['year' => $filters['year']]) }}" class="col-span-2 sm:col-span-4 lg:col-span-1">Clear filters</x-button>
                @endif
            </form>
        </div>

        @if ($pendingAcknowledgements->isNotEmpty())
            <div data-reveal class="rounded-lg border border-warning-300 bg-warning-50 p-4 shadow-sm">
                <p class="text-sm font-semibold text-warning-800">You have {{ $pendingAcknowledgements->count() }} announcement(s) waiting for your confirmation</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($pendingAcknowledgements as $pending)
                        <li><a href="{{ route('news.show', $pending) }}" class="font-medium text-warning-800 underline hover:text-warning-900">{{ $pending->title }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($latestNews->isNotEmpty())
            {{-- Auto-rotates, but pauses while hovered/focused and never auto-rotates
                 for people who asked their OS to reduce motion. --}}
            <div
                x-data="{ active: 0, count: {{ $latestNews->count() }}, paused: false }"
                x-init="count > 1 && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches && setInterval(() => { if (! paused) active = (active + 1) % count }, 6000)"
                @mouseenter="paused = true"
                @mouseleave="paused = false"
                @focusin="paused = true"
                @focusout="paused = false"
                role="region"
                aria-roledescription="carousel"
                aria-label="Latest news"
                data-reveal
                class="relative overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm"
            >
                @foreach ($latestNews as $index => $item)
                    <a
                        href="{{ route('news.show', $item) }}"
                        x-show="active === {{ $index }}"
                        x-transition:enter="transition ease-out duration-500"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-cloak
                        aria-roledescription="slide"
                        class="block cursor-pointer transition hover:brightness-95 {{ $item->template === 'highlight' ? 'relative' : 'grid grid-cols-1 sm:grid-cols-3' }}"
                    >
                        @if ($item->template === 'highlight')
                            <div class="relative h-56 w-full sm:h-72">
                                @if ($item->image_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path) }}" alt="{{ $item->title }}" class="h-full w-full object-cover">
                                @else
                                    <div class="h-full w-full bg-gradient-to-br from-brand-600 to-brand-800"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute bottom-0 left-0 right-0 p-5">
                                    <h3 class="text-lg font-semibold text-white">{{ $item->title }}</h3>
                                    <p class="mt-1 line-clamp-2 text-sm text-neutral-100">{{ \Illuminate\Support\Str::limit(strip_tags($item->body), 160) }}</p>
                                    <p class="mt-1 text-xs text-neutral-300">{{ $item->created_at->format('d M Y') }}</p>
                                </div>
                            </div>
                        @else
                            <div class="sm:col-span-1">
                                @if ($item->image_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path) }}" alt="{{ $item->title }}" class="h-40 w-full object-cover sm:h-full">
                                @else
                                    <div class="h-40 w-full bg-gradient-to-br from-brand-100 to-brand-200 sm:h-full"></div>
                                @endif
                            </div>
                            <div class="p-5 sm:col-span-2">
                                <h3 class="text-base font-semibold text-neutral-900">{{ $item->title }}</h3>
                                <p class="mt-1 line-clamp-3 text-sm text-neutral-600">{{ \Illuminate\Support\Str::limit(strip_tags($item->body), 200) }}</p>
                                <p class="mt-2 text-xs text-neutral-400">{{ $item->created_at->format('d M Y') }}</p>
                            </div>
                        @endif
                    </a>
                @endforeach

                @if ($latestNews->count() > 1)
                    <div class="flex justify-center border-t border-neutral-100 bg-white">
                        @foreach ($latestNews as $index => $item)
                            <button
                                type="button"
                                @click="active = {{ $index }}"
                                aria-label="Show news {{ $index + 1 }} of {{ $latestNews->count() }}"
                                :aria-current="active === {{ $index }} ? 'true' : 'false'"
                                class="group px-1.5 py-3"
                            >
                                <span :class="active === {{ $index }} ? 'bg-brand-600' : 'bg-neutral-300 group-hover:bg-neutral-400'" class="block h-1.5 w-5 rounded-full transition"></span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- KPIs, grouped so the eye can scan "who we have / what needs
             attention" separately from "what we are delivering". --}}
        <section aria-labelledby="kpi-workforce" class="space-y-3">
            <h2 data-reveal id="kpi-workforce" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Workforce &amp; Competency</h2>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
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
                    label="Certificates Expiring"
                    :value="$kpis['certificates_expiring']"
                    icon="{{ $icons['certificate'] }}"
                    color="red"
                    :href="$canViewCertificates ? route('certificates.recertification', ['window' => '60']) : null"
                />
            </div>
        </section>

        <section aria-labelledby="kpi-training" class="space-y-3">
            <h2 data-reveal id="kpi-training" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Training &amp; Development</h2>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
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
                    label="Training Completion ({{ $filters['year'] }})"
                    :value="$kpis['training_completion_rate'].'%'"
                    icon="{{ $icons['check'] }}"
                    color="green"
                    :href="$canViewTraining ? route('training.records.index') : null"
                    :chartType="array_sum($trainingCompletionTrend) > 0 ? 'sparkline' : null"
                    :chartValues="$trainingCompletionTrend"
                    :chartColors="['#2F7532']"
                    :caption="$trainingCompletion['total'] > 0 ? $trainingCompletion['completed'].' of '.$trainingCompletion['total'].' completed' : null"
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
                <x-dashboard-stat
                    label="Overtime Hours ({{ $filters['year'] }})"
                    :value="rtrim(rtrim(number_format($kpis['overtime_hours'], 1), '0'), '.')"
                    icon="{{ $icons['overtime'] }}"
                    color="amber"
                    :href="route('overtime.index')"
                    :chartType="array_sum($overtimeTrend) > 0 ? 'sparkline' : null"
                    :chartValues="$overtimeTrend"
                    :chartColors="['#F0900A']"
                    :caption="array_sum($overtimeTrend) > 0 ? 'Monthly OT hours' : null"
                />
            </div>
        </section>

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

            <x-dashboard-card title="Top Skill Gaps" :empty="$topSkillGaps->isEmpty() ? 'No skill gaps found for the current filters.' : null">
                <ul class="space-y-2.5">
                    @foreach ($topSkillGaps as $row)
                        <li class="flex items-center gap-3 text-xs">
                            <span class="w-32 shrink-0 truncate text-neutral-600" title="{{ $row['skill'] }}">{{ $row['skill'] }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-100">
                                <div data-grow="x" class="h-full rounded-full bg-gradient-to-r from-accent-400 to-accent-600" style="width: {{ round($row['count'] / $maxSkillGap * 100) }}%; --grow-delay: {{ $loop->index * 70 }}ms"></div>
                            </div>
                            <span class="w-6 shrink-0 text-right font-medium tabular-nums text-neutral-900">{{ $row['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-dashboard-card>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-dashboard-card title="Training & Development ({{ $filters['year'] }})">
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
            </x-dashboard-card>

            <x-dashboard-card
                title="Upcoming Training"
                :href="$canViewTraining ? route('training.calendar') : null"
                :empty="$upcomingSessions->isEmpty() ? 'Nothing scheduled.' : null"
            >
                <ul class="space-y-3">
                    @foreach ($upcomingSessions as $session)
                        <li class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-accent-50 text-accent-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
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
                        </li>
                    @endforeach
                </ul>
            </x-dashboard-card>

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

        @php
            $hasGapTrend = array_sum($competencyGapTrend['gap']) + array_sum($competencyGapTrend['meets']) > 0;
        @endphp
        <x-dashboard-card
            title="Competency Gap Trend ({{ $filters['year'] }})"
            :empty="$hasGapTrend ? null : 'No skill assessments recorded in '.$filters['year'].'.'"
        >
            <div class="relative h-56 w-full">
                <canvas
                    data-chart
                    data-chart-type="combo"
                    data-chart-labels="{{ json_encode($competencyGapTrend['labels']) }}"
                    data-chart-values="{{ json_encode($competencyGapTrend['gap']) }}"
                    data-chart-secondary-values="{{ json_encode($competencyGapTrend['meets']) }}"
                    data-chart-bar-label="Assessments Below Requirement"
                    data-chart-line-label="Assessments Meeting/Exceeding"
                ></canvas>
            </div>
            <p class="mt-2 text-[11px] text-neutral-400">Based on each employee's current (latest) assessment per skill, grouped by when it was recorded.</p>
        </x-dashboard-card>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-dashboard-card
                title="Development Priority"
                :href="$canViewDevelopmentPlans ? route('development-plans.index') : null"
                :empty="$developmentPriorities->isEmpty() ? 'No open development plans.' : null"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-[10px] uppercase tracking-wide text-neutral-400">
                                <th scope="col" class="pb-2 pr-2 font-medium">Employee</th>
                                <th scope="col" class="pb-2 pr-2 font-medium">Skill</th>
                                <th scope="col" class="pb-2 pr-2 font-medium">Progress</th>
                                <th scope="col" class="pb-2 font-medium">Priority</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($developmentPriorities as $plan)
                                <tr>
                                    <td class="py-1.5 pr-2 font-medium text-neutral-800">{{ $plan->employee->full_name }}</td>
                                    <td class="py-1.5 pr-2 text-neutral-500">{{ $plan->relatedSkill?->name ?? '—' }}</td>
                                    <td class="py-1.5 pr-2">
                                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-neutral-100" title="{{ $plan->progress_percentage ?? 0 }}%">
                                            <div data-grow="x" class="h-full rounded-full bg-brand-500" style="width: {{ $plan->progress_percentage ?? 0 }}%; --grow-delay: {{ $loop->index * 50 }}ms"></div>
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
            </x-dashboard-card>

            <x-dashboard-donut
                title="Workforce Mix"
                :total="$workforceMix['BC'] + $workforceMix['WCM'] + $workforceMix['INTERN']"
                totalLabel="Employees"
                :segments="[
                    ['label' => 'Blue Collar (BC)', 'value' => $workforceMix['BC'], 'color' => '#01ADEF'],
                    ['label' => 'White Collar Mgmt (WCM)', 'value' => $workforceMix['WCM'], 'color' => '#2F7532'],
                    ['label' => 'Internship', 'value' => $workforceMix['INTERN'], 'color' => '#ec4899'],
                ]"
            />

            <x-dashboard-card
                title="Maintenance Area Distribution"
                :empty="$maintenanceAreaDistribution->isEmpty() ? 'No employees assigned to a maintenance area yet.' : null"
            >
                <div class="flex h-40 items-stretch justify-around gap-2">
                    @foreach ($maintenanceAreaDistribution as $row)
                        @php $pct = max(round($row['total'] / $maxAreaCount * 100), 4); @endphp
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-1">
                            {{-- Percent heights only resolve against a definite-height parent, hence the absolute layout. --}}
                            <div class="relative mt-5 w-full flex-1">
                                <span data-grow-fade class="absolute inset-x-0 mb-1 text-center text-xs font-semibold tabular-nums text-neutral-900" style="bottom: {{ $pct }}%; --grow-delay: {{ $loop->index * 70 }}ms">{{ $row['total'] }}</span>
                                <div data-grow="y" class="absolute inset-x-0 bottom-0 rounded-t-md bg-gradient-to-t from-brand-600 to-brand-400" style="height: {{ $pct }}%; --grow-delay: {{ $loop->index * 70 }}ms"></div>
                            </div>
                            <span class="w-full truncate text-center text-[10px] text-neutral-500" title="{{ $row['area'] }}">{{ $row['area'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-dashboard-card>
        </div>

        <x-dashboard-card title="People Development Insights">
            <x-slot name="action">
                <span class="text-[11px] text-neutral-400">Last updated {{ now()->format('d M Y H:i') }}</span>
            </x-slot>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-md border border-warning-100 bg-warning-50 p-3">
                    <p data-countup class="text-xl font-bold tabular-nums text-warning-700">{{ $insights['employees_needing_development'] }}</p>
                    <p class="text-xs text-warning-800">employees need development</p>
                    <p class="text-[10px] text-warning-600">{{ $insights['employees_needing_development_pct'] }}% of total workforce</p>
                </div>
                <div class="rounded-md border border-danger-100 bg-danger-50 p-3">
                    <p data-countup class="text-xl font-bold tabular-nums text-danger-700">{{ $insights['certificates_needing_renewal'] }}</p>
                    <p class="text-xs text-danger-800">certificates need renewal</p>
                    <p class="text-[10px] text-danger-600">{{ $insights['certificates_needing_renewal_pct'] }}% of total certificates</p>
                </div>
                <div class="rounded-md border border-neutral-200 bg-neutral-50 p-3">
                    <p data-countup class="text-xl font-bold tabular-nums text-neutral-700">{{ $insights['assessments_incomplete'] }}</p>
                    <p class="text-xs text-neutral-800">assessments incomplete</p>
                    <p class="text-[10px] text-neutral-500">{{ $insights['assessments_incomplete_pct'] }}% of total employees</p>
                </div>
                <div class="rounded-md border border-brand-100 bg-brand-50 p-3">
                    <p data-countup class="text-xl font-bold tabular-nums text-brand-700">{{ $insights['critical_skill_gaps'] }}</p>
                    <p class="text-xs text-brand-800">critical skill gaps</p>
                    <p class="text-[10px] text-brand-600">skills with 3+ employees below requirement</p>
                </div>
            </div>
        </x-dashboard-card>
    </div>
</x-app-layout>
